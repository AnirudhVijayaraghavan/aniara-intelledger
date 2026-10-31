<?php

declare(strict_types=1);

use App\Actions\Finance\FinancialActivityCalculator;
use App\Actions\Finance\RecordAccountBalance;
use App\Actions\Finance\UpdateTeamReportingCurrency;
use App\Actions\Finance\VoidAccountBalance;
use App\Enums\TeamRole;
use App\Http\Controllers\Finance\AccountBalanceController;
use App\Http\Controllers\Finance\FinancialActivityController;
use App\Http\Controllers\Finance\TeamReportingCurrencyController;
use App\Http\Requests\Finance\FinancialActivityRequest;
use App\Http\Requests\Finance\RecordAccountBalanceRequest;
use App\Http\Requests\Finance\UpdateReportingCurrencyRequest;
use App\Http\Requests\Finance\VoidAccountBalanceRequest;
use App\Models\AccountBalance;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Validator;

function financialAuthenticationCall(string $operation, mixed $principal, Team $team, FinancialAccount $account, AccountBalance $balance): Closure
{
    $input = match ($operation) {
        'record' => ['amount' => '12.12345678', 'as_of_date' => now()->toDateString()],
        'void' => ['confirmation' => true],
        'activity' => ['start_date' => '2026-10-01', 'end_date' => '2026-10-31'],
        'currency' => ['reporting_currency_code' => 'INR'],
    };
    $request = match ($operation) {
        'record' => new RecordAccountBalanceRequest($input),
        'void' => new VoidAccountBalanceRequest($input),
        'activity' => new FinancialActivityRequest($input),
        'currency' => new UpdateReportingCurrencyRequest($input),
    };
    $request->setUserResolver(fn () => $principal);
    $request->setValidator(Validator::make($input, $request->rules()));

    return match ($operation) {
        'record' => fn () => app(AccountBalanceController::class)->store($request, $team, $account, app(RecordAccountBalance::class)),
        'void' => fn () => app(AccountBalanceController::class)->void($request, $team, $account, $balance, app(VoidAccountBalance::class)),
        'activity' => fn () => app(FinancialActivityController::class)($request, $team, app(FinancialActivityCalculator::class)),
        'currency' => fn () => app(TeamReportingCurrencyController::class)($request, $team, app(UpdateTeamReportingCurrency::class)),
    };
}

test('financial handoffs reject absent or incompatible request principals despite a privileged global guard', function (string $operation, bool $missing) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $before = $balance->fresh()->getAttributes();
    $this->actingAs($owner);
    $principal = $missing ? null : new GenericUser(['id' => $owner->id]);

    expect(financialAuthenticationCall($operation, $principal, $team, $account, $balance))
        ->toThrow(AuthenticationException::class, 'Unauthenticated.');

    $this->assertDatabaseCount('account_balances', 1);
    expect($balance->fresh()->getAttributes())->toBe($before);
    expect($team->fresh()->reporting_currency_code)->toBe('USD');
})->with(['record', 'void', 'activity', 'currency'])->with([false, true]);

test('financial handoffs authorize the request outsider rather than the globally authenticated household owner', function (string $operation) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $outsider = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $before = $balance->fresh()->getAttributes();
    $this->actingAs($owner);

    expect(financialAuthenticationCall($operation, $outsider, $team, $account, $balance))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseCount('account_balances', 1);
    expect($balance->fresh()->getAttributes())->toBe($before);
    expect($team->fresh()->reporting_currency_code)->toBe('USD');
})->with(['record', 'void', 'activity', 'currency']);

test('financial mutation handoffs retain member restrictions despite a globally authenticated owner', function (string $operation) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $before = $balance->fresh()->getAttributes();
    $this->actingAs($owner);

    expect(financialAuthenticationCall($operation, $member, $team, $account, $balance))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseCount('account_balances', 1);
    expect($balance->fresh()->getAttributes())->toBe($before);
    expect($team->fresh()->reporting_currency_code)->toBe('USD');
})->with(['record', 'void', 'currency']);

test('balance mutations record the request actor rather than an unrelated global user', function (string $operation) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $before = $balance->fresh()->getAttributes();
    $this->actingAs(User::factory()->create());

    financialAuthenticationCall($operation, $owner, $team, $account, $balance)();

    if ($operation === 'record') {
        $this->assertDatabaseCount('account_balances', 2);
        $this->assertDatabaseHas('account_balances', ['financial_account_id' => $account->id, 'created_by' => $owner->id, 'amount' => '12.12345678']);
        expect($balance->fresh()->getAttributes())->toBe($before);
    } else {
        $this->assertDatabaseCount('account_balances', 1);
        expect($balance->fresh()->voided_by)->toBe($owner->id);
        expect($balance->fresh()->voided_at)->not->toBeNull();
        expect($balance->fresh()->amount)->toBe($balance->amount);
    }
})->with(['record', 'void']);

test('financial endpoints preserve JSON guest authentication errors without writes', function (string $operation) {
    $account = FinancialAccount::factory()->create();
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $team = $account->team;
    $before = $balance->fresh()->getAttributes();
    $params = ['current_team' => $team->slug, 'financial_account' => $account->id, 'balance' => $balance->id];

    $response = match ($operation) {
        'record' => $this->postJson(route('accounts.balances.store', $params), ['amount' => '12', 'as_of_date' => now()->toDateString()]),
        'void' => $this->postJson(route('accounts.balances.void', $params), ['confirmation' => true]),
        'activity' => $this->getJson(route('financial-activity.index', ['current_team' => $team->slug])),
        'currency' => $this->patchJson(route('teams.reporting-currency.update', ['team' => $team->slug]), ['reporting_currency_code' => 'INR']),
    };

    $response->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    $this->assertDatabaseCount('account_balances', 1);
    expect($balance->fresh()->getAttributes())->toBe($before);
    expect($team->fresh()->reporting_currency_code)->toBe('USD');
})->with(['record', 'void', 'activity', 'currency']);
