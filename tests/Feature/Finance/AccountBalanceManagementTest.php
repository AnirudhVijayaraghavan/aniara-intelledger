<?php

use App\Actions\Finance\RecordAccountBalance;
use App\Enums\AccountBalanceSource;
use App\Enums\TeamRole;
use App\Models\AccountBalance;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Inertia\Testing\AssertableInertia as Assert;

test('owners and admins record manual dated observations with server recording metadata', function (TeamRole $role) {
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(12, 30, 45, 123456));
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($actor, ['role' => $role->value]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);

    $this->actingAs($actor)->post(route('accounts.balances.store', ['current_team' => $team->slug, 'financial_account' => $account->id]), [
        'amount' => '123.12345678', 'as_of_date' => '2026-10-23',
    ])->assertSessionHasNoErrors()->assertRedirectToRoute('accounts.show', ['current_team' => $team->slug, 'financial_account' => $account->id]);

    $balance = AccountBalance::sole();
    expect($balance->team_id)->toBe($team->id);
    expect($balance->financial_account_id)->toBe($account->id);
    expect($balance->amount)->toBe('123.12345678');
    expect($balance->as_of_date->toDateString())->toBe('2026-10-23');
    expect($balance->recorded_at->format('Y-m-d H:i:s.u'))->toBe('2026-10-24 12:30:45.123456');
    expect($balance->source)->toBe(AccountBalanceSource::Manual);
    expect($balance->created_by)->toBe($actor->id);
    expect($balance->voided_at)->toBeNull();
})->with([TeamRole::Owner, TeamRole::Admin]);

test('decimal strings round trip exactly through persistence and Inertia without changing account type semantics', function (string $type, string $amount, string $expected) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id, 'type' => $type]);

    $this->actingAs($actor)->post(route('accounts.balances.store', ['current_team' => $team->slug, 'financial_account' => $account->id]), [
        'amount' => $amount, 'as_of_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect(AccountBalance::sole()->amount)->toBe($expected);
    $this->get(route('accounts.show', ['current_team' => $team->slug, 'financial_account' => $account->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('accounts/show')
        ->where('currentBalance.amount', $expected)->where('balances.data.0.amount', $expected)
        ->where('account.currency_code', 'USD')->where('canRecordBalance', true)->where('canVoidBalances', true));
})->with([
    'maximum asset' => ['checking', '9999999999999999.99999999', '9999999999999999.99999999'],
    'maximum negative asset' => ['checking', '-9999999999999999.99999999', '-9999999999999999.99999999'],
    'small asset' => ['savings', '0.00000001', '0.00000001'],
    'owed liability' => ['loan', '1234567890123456.12345678', '1234567890123456.12345678'],
    'overpaid liability' => ['credit_card', '-50.25', '-50.25000000'],
    'zero' => ['brokerage', '0', '0.00000000'],
]);

test('same date and recording timestamp observations remain distinct and the higher id wins', function () {
    $this->freezeTime();
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $parameters = ['current_team' => $team->slug, 'financial_account' => $account->id];

    $this->actingAs($actor)->post(route('accounts.balances.store', $parameters), ['amount' => '100', 'as_of_date' => now()->toDateString()])->assertSessionHasNoErrors();
    $first = AccountBalance::sole();
    $this->post(route('accounts.balances.store', $parameters), ['amount' => '101', 'as_of_date' => now()->toDateString()])->assertSessionHasNoErrors();
    $second = AccountBalance::orderByDesc('id')->firstOrFail();

    expect($first->fresh()->amount)->toBe('100.00000000');
    expect($first->recorded_at->equalTo($second->recorded_at))->toBeTrue();
    $this->assertDatabaseCount('account_balances', 2);
    $this->get(route('accounts.show', $parameters))->assertInertia(fn (Assert $page) => $page
        ->where('currentBalance.id', $second->id)->where('balances.data.0.id', $second->id)->where('balances.data.1.id', $first->id));
});

test('history orders by economic date then recording microseconds then id and selects the latest non voided observation', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $first = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'as_of_date' => '2026-10-20', 'recorded_at' => '2026-10-24 10:00:00.000001']);
    $second = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'as_of_date' => '2026-10-20', 'recorded_at' => '2026-10-24 10:00:00.000002']);
    $third = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'as_of_date' => '2026-10-20', 'recorded_at' => '2026-10-24 10:00:00.000002']);
    $backdated = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'as_of_date' => '2026-10-19', 'recorded_at' => '2026-10-24 23:00:00']);
    $latest = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'as_of_date' => '2026-10-21', 'recorded_at' => '2026-10-23 10:00:00']);
    $voided = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'as_of_date' => '2026-10-22', 'voided_at' => now(), 'voided_by' => $actor->id]);

    $this->actingAs($actor)->get(route('accounts.show', ['current_team' => $team->slug, 'financial_account' => $account->id]))
        ->assertInertia(fn (Assert $page) => $page->where('currentBalance.id', $latest->id)
            ->where('balances.data.0.id', $voided->id)->where('balances.data.1.id', $latest->id)
            ->where('balances.data.2.id', $third->id)->where('balances.data.3.id', $second->id)
            ->where('balances.data.4.id', $first->id)->where('balances.data.5.id', $backdated->id)
            ->where('balances.data.0.voided_by', $actor->id)->where('balances.data.0.voided_by_name', $actor->name));
});

test('voiding preserves observation data and actor metadata and falls back to the preceding observation', function () {
    $this->freezeTime();
    $actor = User::factory()->create();
    $admin = User::factory()->create();
    $team = $actor->personalTeam();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $older = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '42.12345678', 'as_of_date' => now()->subDay()->toDateString()]);
    $latest = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '43.12345678', 'created_by' => $actor->id]);
    $originalRecordedAt = $latest->recorded_at->toISOString();
    $parameters = ['current_team' => $team->slug, 'financial_account' => $account->id, 'balance' => $latest->id];

    $this->actingAs($admin)->post(route('accounts.balances.void', $parameters))->assertSessionHasErrors('confirmation');
    expect($latest->fresh()->voided_at)->toBeNull();
    $this->post(route('accounts.balances.void', $parameters), ['confirmation' => true])->assertSessionHasNoErrors()->assertRedirect();
    expect($latest->fresh()->amount)->toBe('43.12345678');
    expect($latest->fresh()->created_by)->toBe($actor->id);
    expect($latest->fresh()->recorded_at->toISOString())->toBe($originalRecordedAt);
    expect($latest->fresh()->voided_by)->toBe($admin->id);
    expect($latest->fresh()->voided_at->equalTo(now()->startOfSecond()))->toBeTrue();
    $this->assertDatabaseCount('account_balances', 2);

    $this->get(route('accounts.show', ['current_team' => $team->slug, 'financial_account' => $account->id]))
        ->assertInertia(fn (Assert $page) => $page->where('currentBalance.id', $older->id)->has('balances.data', 2));
    $this->actingAs($actor)->post(route('accounts.balances.void', $parameters), ['confirmation' => true])->assertForbidden();
    expect($latest->fresh()->voided_by)->toBe($admin->id);
});

test('account pages distinguish an empty history from a history containing only voided observations', function (bool $hasVoidedHistory) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    if ($hasVoidedHistory) {
        AccountBalance::factory()->create(['financial_account_id' => $account->id, 'voided_at' => now(), 'voided_by' => $actor->id]);
    }

    $this->actingAs($actor)->get(route('accounts.show', ['current_team' => $team->slug, 'financial_account' => $account->id]))
        ->assertInertia(fn (Assert $page) => $page->where('currentBalance', null)
            ->where('balances.total', $hasVoidedHistory ? 1 : 0)->where('canVoidBalances', false)->where('canRecordBalance', true));
})->with([false, true]);

test('balance history is paginated without changing the selected current balance', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    AccountBalance::factory()->count(26)->create(['financial_account_id' => $account->id, 'recorded_at' => '2026-10-24 12:00:00', 'as_of_date' => '2026-10-24']);
    $latestId = AccountBalance::max('id');

    $this->actingAs($actor)->get(route('accounts.show', ['current_team' => $team->slug, 'financial_account' => $account->id, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->where('currentBalance.id', $latestId)
            ->where('balances.current_page', 2)->where('balances.total', 26)->has('balances.data', 1));
});

test('members can read balances but cannot record or void observations', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $parameters = ['current_team' => $team->slug, 'financial_account' => $account->id];

    $this->actingAs($actor)->get(route('accounts.show', $parameters))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('currentBalance.id', $balance->id)->where('canRecordBalance', false)->where('canVoidBalances', false));
    $this->post(route('accounts.balances.store', $parameters), ['amount' => '10', 'as_of_date' => now()->toDateString()])->assertForbidden();
    $this->post(route('accounts.balances.void', $parameters + ['balance' => $balance->id]), ['confirmation' => true])->assertForbidden();
    expect($balance->fresh()->voided_at)->toBeNull();
    $this->assertDatabaseCount('account_balances', 1);
});

test('guests must authenticate to record or void balances', function (string $routeName) {
    $balance = AccountBalance::factory()->create();
    $account = $balance->account;

    $this->post(route($routeName, ['current_team' => $account->team->slug, 'financial_account' => $account->id, 'balance' => $balance->id]), [
        'amount' => '10', 'as_of_date' => now()->toDateString(), 'confirmation' => true,
    ])->assertRedirectToRoute('login');
})->with(['accounts.balances.store', 'accounts.balances.void']);

test('outsiders cannot read or write balance data in another household', function () {
    $actor = User::factory()->create();
    $balance = AccountBalance::factory()->create();
    $account = $balance->account;
    $parameters = ['current_team' => $account->team->slug, 'financial_account' => $account->id];

    $this->actingAs($actor)->get(route('accounts.show', $parameters))->assertForbidden();
    $this->post(route('accounts.balances.store', $parameters), ['amount' => '10', 'as_of_date' => now()->toDateString()])->assertForbidden();
    $this->post(route('accounts.balances.void', $parameters + ['balance' => $balance->id]), ['confirmation' => true])->assertForbidden();
    expect($balance->fresh()->voided_at)->toBeNull();
    $this->assertDatabaseCount('account_balances', 1);
});

test('scoped balance routes reject another teams account even for an actor belonging to both', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $otherTeam = User::factory()->create()->personalTeam();
    $otherTeam->members()->attach($actor, ['role' => TeamRole::Admin->value]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => FinancialAccount::factory()->create(['team_id' => $otherTeam->id])->id]);
    $parameters = ['current_team' => $team->slug, 'financial_account' => $balance->financial_account_id];

    $this->actingAs($actor)->post(route('accounts.balances.store', $parameters), ['amount' => '10', 'as_of_date' => now()->toDateString()])->assertNotFound();
    $this->post(route('accounts.balances.void', $parameters + ['balance' => $balance->id]), ['confirmation' => true])->assertNotFound();
    expect($balance->fresh()->voided_at)->toBeNull();
});

test('balance ids must belong to the nested account and team', function (bool $sameTeam) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $otherAccount = FinancialAccount::factory()->create(['team_id' => $sameTeam ? $team->id : Team::factory()->create()->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $otherAccount->id]);

    $this->actingAs($actor)->post(route('accounts.balances.void', ['current_team' => $team->slug, 'financial_account' => $account->id, 'balance' => $balance->id]), ['confirmation' => true])->assertNotFound();
    expect($balance->fresh()->voided_at)->toBeNull();
})->with([true, false]);

test('archived accounts retain readable and voidable history but reject new balances', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id, 'archived_at' => now()]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $parameters = ['current_team' => $team->slug, 'financial_account' => $account->id];

    $this->actingAs($actor)->get(route('accounts.show', $parameters))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('currentBalance.id', $balance->id)->where('canRecordBalance', false)->where('canVoidBalances', true));
    $this->post(route('accounts.balances.store', $parameters), ['amount' => '10', 'as_of_date' => now()->toDateString()])->assertForbidden();
    $this->post(route('accounts.balances.void', $parameters + ['balance' => $balance->id]), ['confirmation' => true])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('account_balances', 1);
    expect($balance->fresh()->voided_by)->toBe($actor->id);
});

test('recording rechecks account archival after acquiring locks rather than trusting a stale model', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $account->fresh()->update(['archived_at' => now()]);

    expect(fn () => app(RecordAccountBalance::class)->handle($actor, $team, $account, ['amount' => '10', 'as_of_date' => now()->toDateString()]))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('account_balances', 0);
});

test('invalid amounts and dates return validation errors and persist nothing', function (mixed $value, string $field) {
    $this->travelTo(now()->setDate(2026, 10, 24)->startOfDay());
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $input = ['amount' => '10.00', 'as_of_date' => '2026-10-24'];
    $input[$field] = $value;

    $this->actingAs($actor)->postJson(route('accounts.balances.store', ['current_team' => $team->slug, 'financial_account' => $account->id]), $input)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('account_balances', 0);
})->with([
    'missing amount' => [null, 'amount'],
    'numeric JSON amount' => [1.25, 'amount'],
    'numeric JSON integer' => [10, 'amount'],
    'excess scale' => ['0.123456789', 'amount'],
    'positive overflow' => ['10000000000000000', 'amount'],
    'negative overflow' => ['-10000000000000000', 'amount'],
    'exponent' => ['1e3', 'amount'],
    'comma separator' => ['1,234.56', 'amount'],
    'NaN' => ['NaN', 'amount'],
    'non scalar amount' => [['10.00'], 'amount'],
    'missing date' => [null, 'as_of_date'],
    'future date' => ['2026-10-25', 'as_of_date'],
    'impossible date' => ['2026-02-30', 'as_of_date'],
    'timestamp instead of date' => ['2026-10-24T12:00:00Z', 'as_of_date'],
]);

test('client supplied tenant source actor and observation metadata are rejected', function (string $field) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $input = ['amount' => '10.00', 'as_of_date' => now()->toDateString(), $field => '123'];

    $this->actingAs($actor)->post(route('accounts.balances.store', ['current_team' => $team->slug, 'financial_account' => $account->id]), $input)->assertSessionHasErrors($field);
    $this->assertDatabaseCount('account_balances', 0);
})->with(['team_id', 'financial_account_id', 'source', 'recorded_at', 'created_by', 'voided_at', 'voided_by']);

test('void requests cannot change the original observation or supply audit metadata', function (string $field) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);

    $this->actingAs($actor)->post(route('accounts.balances.void', ['current_team' => $team->slug, 'financial_account' => $account->id, 'balance' => $balance->id]), ['confirmation' => true, $field => '123'])->assertSessionHasErrors($field);
    expect($balance->fresh()->amount)->toBe('100.00000000');
    expect($balance->fresh()->voided_at)->toBeNull();
})->with(['amount', 'as_of_date', 'recorded_at', 'source', 'created_by', 'voided_at', 'voided_by', 'team_id', 'financial_account_id']);

test('balance observations expose no edit or delete HTTP operations', function (string $method) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);

    $this->actingAs($actor)->call($method, route('accounts.balances.void', ['current_team' => $team->slug, 'financial_account' => $account->id, 'balance' => $balance->id]), ['amount' => '999', 'confirmation' => true])->assertMethodNotAllowed();
    expect($balance->fresh()->amount)->toBe('100.00000000');
    expect($balance->fresh()->voided_at)->toBeNull();
})->with(['PUT', 'PATCH', 'DELETE']);

test('model operations cannot reinstate or reattribute a voided observation', function (array $changes) {
    $actor = User::factory()->create();
    $balance = AccountBalance::factory()->create(['voided_at' => now(), 'voided_by' => $actor->id]);

    expect(fn () => $balance->update($changes))->toThrow(DomainException::class, 'Voided observations cannot be reinstated or reattributed.');
    expect($balance->fresh()->voided_at)->not->toBeNull();
    expect($balance->fresh()->voided_by)->toBe($actor->id);
})->with([
    'reinstate' => [['voided_at' => null, 'voided_by' => null]],
    'reattribute' => [['voided_by' => null]],
]);
