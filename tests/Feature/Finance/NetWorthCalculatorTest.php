<?php

declare(strict_types=1);

use App\Actions\Finance\NetWorthCalculator;
use App\Data\AccountNetWorthContribution;
use App\Data\CurrencyNetWorth;
use App\Data\NetWorthResult;
use App\Enums\TeamRole;
use App\Models\AccountBalance;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountOwner;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @param array<string, mixed> $observation */
function netWorthObservation(Team $team, string $type, string $currency, string $amount, array $observation = []): AccountBalance
{
    $account = FinancialAccount::factory()->create(['team_id' => $team->id, 'type' => $type, 'currency_code' => $currency]);

    return AccountBalance::factory()->create([
        'financial_account_id' => $account->id,
        'amount' => $amount,
        'as_of_date' => '2026-10-20',
        'recorded_at' => '2026-10-21 12:00:00.123456',
        ...$observation,
    ]);
}

test('single currency households have exact assets liabilities type subtotals and account contributions', function (string $currency) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    netWorthObservation($team, 'checking', $currency, '100.10');
    netWorthObservation($team, 'checking', $currency, '0.20');
    netWorthObservation($team, 'savings', $currency, '200.20');
    netWorthObservation($team, 'brokerage', $currency, '300.30');
    netWorthObservation($team, 'loan', $currency, '50.05');
    $card = netWorthObservation($team, 'credit_card', $currency, '10.01');

    $result = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect($result)->toBeInstanceOf(NetWorthResult::class);
    expect($result->teamId)->toBe($team->id);
    expect($result->asOfDate)->toBe('2026-10-20');
    expect(array_keys($result->currencies))->toBe([$currency]);
    $totals = $result->currencies[$currency];
    expect($totals)->toBeInstanceOf(CurrencyNetWorth::class);
    expect($totals->totalAssets)->toBe('600.80000000');
    expect($totals->totalLiabilities)->toBe('60.06000000');
    expect($totals->netWorth)->toBe('540.74000000');
    expect($totals->assetsByAccountType)->toBe(['brokerage' => '300.30000000', 'checking' => '100.30000000', 'savings' => '200.20000000']);
    expect($totals->liabilitiesByAccountType)->toBe(['credit_card' => '10.01000000', 'loan' => '50.05000000']);
    expect($totals->accounts)->toHaveCount(6);
    expect($totals->missingAccountIds)->toBe([]);
    expect($result->isComplete)->toBeTrue();
    $contribution = $totals->accounts[5];
    expect($contribution)->toBeInstanceOf(AccountNetWorthContribution::class);
    expect($contribution->balanceObservationId)->toBe($card->id);
    expect($contribution->balanceAmount)->toBe('10.01000000');
    expect($contribution->netWorthContribution)->toBe('-10.01000000');
    expect($contribution->balanceAsOfDate)->toBe('2026-10-20');
    expect($contribution->balanceRecordedAt)->toBe('2026-10-21T12:00:00.123456Z');
})->with(['USD', 'INR']);

test('mixed currency households never combine USD and INR totals', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    netWorthObservation($team, 'checking', 'USD', '100');
    netWorthObservation($team, 'credit_card', 'USD', '25');
    netWorthObservation($team, 'savings', 'INR', '100000');
    netWorthObservation($team, 'loan', 'INR', '30000');

    $result = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect(array_keys($result->currencies))->toBe(['INR', 'USD']);
    expect($result->currencies['USD']->totalAssets)->toBe('100.00000000');
    expect($result->currencies['USD']->totalLiabilities)->toBe('25.00000000');
    expect($result->currencies['USD']->netWorth)->toBe('75.00000000');
    expect($result->currencies['INR']->totalAssets)->toBe('100000.00000000');
    expect($result->currencies['INR']->totalLiabilities)->toBe('30000.00000000');
    expect($result->currencies['INR']->netWorth)->toBe('70000.00000000');
    expect(array_keys($result->jsonSerialize()))->not->toContain('netWorth');
});

test('negative asset and liability balances retain their signed financial meaning', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    netWorthObservation($team, 'checking', 'USD', '-50.25');
    netWorthObservation($team, 'credit_card', 'USD', '-20.10');
    netWorthObservation($team, 'other_liability', 'USD', '5.05');

    $result = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20')->currencies['USD'];

    expect($result->totalAssets)->toBe('-50.25000000');
    expect($result->totalLiabilities)->toBe('-15.05000000');
    expect($result->netWorth)->toBe('-35.20000000');
    expect($result->assetsByAccountType)->toBe(['checking' => '-50.25000000']);
    expect($result->liabilitiesByAccountType)->toBe(['credit_card' => '-20.10000000', 'other_liability' => '5.05000000']);
    expect($result->accounts[0]->netWorthContribution)->toBe('-50.25000000');
    expect($result->accounts[1]->netWorthContribution)->toBe('20.10000000');
});

test('valuation uses economic date recording microseconds and id while excluding voided and later dated balances', function (string $asOfDate, ?string $expected) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $first = netWorthObservation($team, 'savings', 'USD', '100', ['recorded_at' => '2026-10-25 12:00:00.123450']);
    $account = $first->account;
    AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '110', 'as_of_date' => '2026-10-20', 'recorded_at' => '2026-10-25 12:00:00.123451']);
    $correction = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '120', 'as_of_date' => '2026-10-20', 'recorded_at' => '2026-10-25 12:00:00.123451']);
    AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '999', 'as_of_date' => '2026-10-20', 'recorded_at' => '2026-10-25 12:00:00.123450']);
    AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '200', 'as_of_date' => '2026-10-21', 'recorded_at' => '2026-10-22 12:00:00']);
    AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '900', 'as_of_date' => '2026-10-21', 'recorded_at' => '2026-10-26 12:00:00', 'voided_at' => '2026-10-27 12:00:00', 'voided_by' => $actor->id]);
    AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '300', 'as_of_date' => '2026-10-22', 'recorded_at' => '2026-10-23 12:00:00']);

    $result = app(NetWorthCalculator::class)->handle($actor, $team, $asOfDate)->currencies['USD'];

    expect($result->accounts)->toHaveCount(1);
    expect($result->accounts[0]->balanceAmount)->toBe($expected);
    expect($result->accounts[0]->missingData)->toBe($expected === null);
    if ($asOfDate === '2026-10-20') {
        expect($result->accounts[0]->balanceObservationId)->toBe($correction->id);
    }
})->with([
    ['2026-10-19', null], ['2026-10-20', '120.00000000'],
    ['2026-10-21', '200.00000000'], ['2026-10-22', '300.00000000'],
]);

test('archiving retains the last known value until an explicit closing zero observation', function (bool $hasClosingZero) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $balance = netWorthObservation($team, 'brokerage', 'INR', '1000', ['as_of_date' => '2026-10-01']);
    $account = $balance->account;
    $account->update(['archived_at' => '2026-10-15 12:00:00']);
    if ($hasClosingZero) {
        AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '0', 'as_of_date' => '2026-10-18']);
    }

    $before = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-10');
    $after = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect($before->currencies['INR']->netWorth)->toBe('1000.00000000');
    expect($after->currencies['INR']->netWorth)->toBe($hasClosingZero ? '0.00000000' : '1000.00000000');
    expect($after->currencies['INR']->accounts)->toHaveCount(1);
    expect($after->currencies['INR']->accounts[0]->archivedAt)->toBe('2026-10-15T12:00:00.000000Z');
    expect($after->isComplete)->toBeTrue();
})->with([false, true]);

test('missing histories future only histories and all voided histories remain explicit missing data', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    netWorthObservation($team, 'checking', 'USD', '10');
    $missing = FinancialAccount::factory()->create(['team_id' => $team->id, 'type' => 'loan']);
    $future = netWorthObservation($team, 'savings', 'USD', '20', ['as_of_date' => '2026-10-21']);
    $voided = netWorthObservation($team, 'other_asset', 'USD', '30', ['voided_at' => '2026-10-22 12:00:00', 'voided_by' => $actor->id]);
    $archived = FinancialAccount::factory()->create(['team_id' => $team->id, 'currency_code' => 'INR', 'archived_at' => '2026-10-19 12:00:00']);

    $result = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect($result->isComplete)->toBeFalse();
    expect($result->currencies['USD']->totalAssets)->toBe('10.00000000');
    expect($result->currencies['USD']->totalLiabilities)->toBe('0.00000000');
    expect($result->currencies['USD']->assetsByAccountType)->toBe(['checking' => '10.00000000']);
    expect($result->currencies['USD']->liabilitiesByAccountType)->toBe([]);
    expect($result->currencies['USD']->missingAccountIds)->toBe([$missing->id, $future->financial_account_id, $voided->financial_account_id]);
    expect($result->currencies['INR']->missingAccountIds)->toBe([$archived->id]);
    foreach ($result->currencies['USD']->accounts as $contribution) {
        if ($contribution->accountId !== $missing->id) {
            continue;
        }
        expect($contribution->missingData)->toBeTrue();
        expect($contribution->balanceObservationId)->toBeNull();
        expect($contribution->balanceAmount)->toBeNull();
        expect($contribution->netWorthContribution)->toBeNull();
    }
});

test('an observed zero is complete data and an empty household does not invent currency totals', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $empty = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20');
    expect($empty->currencies)->toBe([]);
    expect($empty->isComplete)->toBeTrue();
    expect(json_decode(json_encode($empty, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR)->currencies)->toBeInstanceOf(stdClass::class);

    netWorthObservation($team, 'checking', 'USD', '0');
    $result = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect($result->isComplete)->toBeTrue();
    expect($result->currencies['USD']->accounts[0]->missingData)->toBeFalse();
    expect($result->currencies['USD']->accounts[0]->balanceAmount)->toBe('0.00000000');
});

test('multiple recorded holders never multiply account values or aggregate brokerage observations', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $balance = netWorthObservation($team, 'brokerage', 'USD', '500');
    FinancialAccountOwner::factory()->count(3)->create(['team_id' => $team->id, 'financial_account_id' => $balance->financial_account_id]);
    AccountBalance::factory()->create(['financial_account_id' => $balance->financial_account_id, 'amount' => '450', 'as_of_date' => '2026-10-19']);

    $result = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20')->currencies['USD'];

    expect($result->accounts)->toHaveCount(1);
    expect($result->totalAssets)->toBe('500.00000000');
    expect($result->assetsByAccountType)->toBe(['brokerage' => '500.00000000']);
});

test('explicit team membership authorizes calculations without changing or depending on current team preference', function () {
    $actor = User::factory()->create();
    $personalTeam = $actor->personalTeam();
    $team = User::factory()->create()->personalTeam();
    $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    netWorthObservation($personalTeam, 'checking', 'USD', '999999');
    netWorthObservation($team, 'savings', 'INR', '500');

    $result = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect(array_keys($result->currencies))->toBe(['INR']);
    expect($result->currencies['INR']->netWorth)->toBe('500.00000000');
    expect($actor->fresh()->current_team_id)->toBe($personalTeam->id);
});

test('unauthorized or revoked membership cannot be replaced by a matching current team preference', function (bool $revoked) {
    $actor = User::factory()->create();
    $team = User::factory()->create()->personalTeam();
    if ($revoked) {
        $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
        $team->members()->detach($actor);
    }
    $actor->update(['current_team_id' => $team->id]);
    netWorthObservation($team, 'checking', 'USD', '100');

    expect(fn () => app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20'))->toThrow(AuthorizationException::class);
})->with([false, true]);

test('a stale team instance cannot expose a retired households financial data', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    netWorthObservation($team, 'checking', 'USD', '100');
    $team->fresh()->delete();

    expect(fn () => app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20'))->toThrow(ModelNotFoundException::class);
});

test('decimal arithmetic retains full account precision and allows aggregate totals beyond individual account precision', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    netWorthObservation($team, 'checking', 'USD', '9999999999999999.99999999');
    netWorthObservation($team, 'checking', 'USD', '9999999999999999.99999999');
    netWorthObservation($team, 'loan', 'USD', '0.00000001');

    $result = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20');
    $json = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

    expect($result->currencies['USD']->totalAssets)->toBe('19999999999999999.99999998');
    expect($result->currencies['USD']->totalLiabilities)->toBe('0.00000001');
    expect($result->currencies['USD']->netWorth)->toBe('19999999999999999.99999997');
    expect($json['currencies']['USD']['netWorth'])->toBe('19999999999999999.99999997');
    expect($json['currencies']['USD']['accounts'][2]['type'])->toBe('loan');
    expect($json['currencies']['USD']['accounts'][2]['netWorthContribution'])->toBe('-0.00000001');
});

test('calculation query count and hydrated history stay bounded and all database operations are reads', function (int $accountCount) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $accounts = FinancialAccount::factory()->count($accountCount)->create(['team_id' => $team->id]);
    foreach ($accounts as $account) {
        AccountBalance::factory()->count(5)->create(['financial_account_id' => $account->id, 'as_of_date' => '2026-10-20']);
    }
    $hydratedBalances = 0;
    AccountBalance::retrieved(function () use (&$hydratedBalances): void {
        $hydratedBalances++;
    });
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $result = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20');
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($result->currencies['USD']->accounts)->toHaveCount($accountCount);
    expect($hydratedBalances)->toBeLessThanOrEqual($accountCount);
    expect(array_all($queries, fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select')))->toBeTrue();
    expect(array_values(array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'financial_accounts') || str_contains($query['query'], 'account_balances'))))->toHaveCount(1);
    $this->assertDatabaseCount('account_balances', $accountCount * 5);
})->with([1, 20]);

test('repeated calculations with unchanged records return identical serialized results', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    netWorthObservation($team, 'checking', 'USD', '0.1');
    netWorthObservation($team, 'savings', 'INR', '0.2');

    $first = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20');
    $second = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect(json_encode($first, JSON_THROW_ON_ERROR))->toBe(json_encode($second, JSON_THROW_ON_ERROR));
});

test('valuation dates must be valid ISO economic dates', function (string $asOfDate) {
    $actor = User::factory()->create();

    expect(fn () => app(NetWorthCalculator::class)->handle($actor, $actor->personalTeam(), $asOfDate))->toThrow(ValidationException::class);
})->with(['', '2026-02-30', '2026-10-20T12:00:00Z', '20/10/2026', "2026-10-20' OR 1=1"]);
