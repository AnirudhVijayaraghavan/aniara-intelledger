<?php

declare(strict_types=1);

use App\Actions\Finance\ConsolidatedNetWorthCalculator;
use App\Actions\Finance\NetWorthCalculator;
use App\Enums\TeamRole;
use App\Models\AccountBalance;
use App\Models\ExchangeRate;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

function consolidatedObservation(Team $team, string $currency, string $type, string $amount): AccountBalance
{
    $account = FinancialAccount::factory()->create(['team_id' => $team->id, 'currency_code' => $currency, 'type' => $type]);

    return AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => $amount, 'as_of_date' => '2026-10-20', 'recorded_at' => '2026-10-21 12:00:00.123456']);
}

test('consolidation rejects a valuation date that cannot produce a valid ISO timestamp', function () {
    $actor = User::factory()->create();

    expect(fn () => app(ConsolidatedNetWorthCalculator::class)->handle($actor, $actor->personalTeam(), '0000-01-01'))
        ->toThrow(InvalidArgumentException::class, 'The net-worth valuation timestamp must be valid.');
    $this->assertDatabaseCount('account_balances', 0);
});

test('mixed currency valuations preserve original totals and consolidate in the teams reporting currency', function (string $reporting, string $assets, string $liabilities, string $netWorth) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $team->update(['reporting_currency_code' => $reporting]);
    consolidatedObservation($team, 'USD', 'checking', '100');
    consolidatedObservation($team, 'USD', 'loan', '20');
    consolidatedObservation($team, 'INR', 'savings', '8000');
    consolidatedObservation($team, 'INR', 'credit_card', '1600');
    $rate = ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => '80', 'effective_at' => '2026-10-20T15:00:00Z']);
    $original = app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    $result = app(ConsolidatedNetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect($result->isComplete)->toBeTrue();
    expect($result->reportingCurrencyCode)->toBe($reporting);
    expect($result->totalAssets)->toBe($assets);
    expect($result->totalLiabilities)->toBe($liabilities);
    expect($result->netWorth)->toBe($netWorth);
    expect($result->valuationAt)->toBe('2026-10-20T23:59:59.999999Z');
    expect(json_encode($result->original, JSON_THROW_ON_ERROR))->toBe(json_encode($original, JSON_THROW_ON_ERROR));
    $fx = $result->fxQuotes[$reporting === 'USD' ? 'INR' : 'USD'];
    expect($fx->rateId)->toBe($rate->id);
    expect($fx->rate)->toBe('80.000000000000000000');
    expect($fx->source)->toBe('manual');
    expect($fx->isInverse)->toBe($reporting === 'USD');
})->with([
    ['USD', '200.00000000', '40.00000000', '160.00000000'],
    ['INR', '16000.00000000', '3200.00000000', '12800.00000000'],
]);

test('missing balance observations suppress all definitive consolidated totals even when FX is available', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    consolidatedObservation($team, 'INR', 'savings', '800');
    $missing = FinancialAccount::factory()->create(['team_id' => $team->id]);
    ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => '80', 'effective_at' => '2026-10-20T00:00:00Z']);

    $result = app(ConsolidatedNetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect($result->isComplete)->toBeFalse();
    expect($result->totalAssets)->toBeNull();
    expect($result->totalLiabilities)->toBeNull();
    expect($result->netWorth)->toBeNull();
    expect($result->missingRateCurrencies)->toBe([]);
    expect($result->original->currencies['USD']->missingAccountIds)->toBe([$missing->id]);
    expect($result->original->currencies['INR']->totalAssets)->toBe('800.00000000');
});

test('missing or future only FX observations make consolidated valuations incomplete without hiding original balances', function (bool $hasFutureRate) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    consolidatedObservation($team, 'USD', 'checking', '10');
    consolidatedObservation($team, 'INR', 'savings', '800');
    if ($hasFutureRate) {
        ExchangeRate::factory()->create(['team_id' => $team->id, 'effective_at' => '2026-10-21T00:00:00Z']);
    }

    $result = app(ConsolidatedNetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect($result->isComplete)->toBeFalse();
    expect($result->original->isComplete)->toBeTrue();
    expect($result->original->currencies['INR']->netWorth)->toBe('800.00000000');
    expect($result->missingRateCurrencies)->toBe(['INR']);
    expect($result->totalAssets)->toBeNull();
    expect($result->totalLiabilities)->toBeNull();
    expect($result->netWorth)->toBeNull();
    expect($result->fxQuotes['USD']->source)->toBe('identity');
})->with([false, true]);

test('historical consolidation selects eligible FX rather than todays latest rate', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    consolidatedObservation($team, 'INR', 'savings', '800');
    $old = ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => '80', 'effective_at' => '2026-10-20T00:00:00Z']);
    ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => '100', 'effective_at' => '2026-10-25T00:00:00Z']);

    $result = app(ConsolidatedNetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect($result->netWorth)->toBe('10.00000000');
    expect($result->fxQuotes['INR']->rateId)->toBe($old->id);
});

test('conversion rounds each currency subtotal once instead of rounding individual accounts', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    consolidatedObservation($team, 'INR', 'checking', '0.00000001');
    consolidatedObservation($team, 'INR', 'savings', '0.00000001');
    ExchangeRate::factory()->create(['team_id' => $team->id, 'base_currency_code' => 'INR', 'quote_currency_code' => 'USD', 'rate' => '0.5', 'effective_at' => '2026-10-20T00:00:00Z']);

    $result = app(ConsolidatedNetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect($result->totalAssets)->toBe('0.00000001');
    expect($result->netWorth)->toBe('0.00000001');
});

test('consolidated net worth is converted assets minus converted liabilities after half even rounding', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    consolidatedObservation($team, 'INR', 'checking', '0.00000001');
    consolidatedObservation($team, 'INR', 'loan', '0.00000003');
    ExchangeRate::factory()->create(['team_id' => $team->id, 'base_currency_code' => 'INR', 'quote_currency_code' => 'USD', 'rate' => '0.5', 'effective_at' => '2026-10-20T00:00:00Z']);

    $result = app(ConsolidatedNetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect($result->totalAssets)->toBe('0.00000000');
    expect($result->totalLiabilities)->toBe('0.00000002');
    expect($result->netWorth)->toBe('-0.00000002');
});

test('negative asset and liability balances remain signed through consolidation', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    consolidatedObservation($team, 'USD', 'checking', '-10');
    consolidatedObservation($team, 'INR', 'credit_card', '-80');
    ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => '80', 'effective_at' => '2026-10-20T00:00:00Z']);

    $result = app(ConsolidatedNetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect($result->totalAssets)->toBe('-10.00000000');
    expect($result->totalLiabilities)->toBe('-1.00000000');
    expect($result->netWorth)->toBe('-9.00000000');
});

test('households with no cross currency exposure require no FX rates', function (bool $empty) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    if (! $empty) {
        consolidatedObservation($team, 'USD', 'checking', '10');
    }

    $result = app(ConsolidatedNetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect($result->isComplete)->toBeTrue();
    expect($result->netWorth)->toBe($empty ? '0.00000000' : '10.00000000');
    expect($result->missingRateCurrencies)->toBe([]);
    $this->assertDatabaseCount('exchange_rates', 0);
})->with([false, true]);

test('consolidation uses the explicit authorized team setting and rates rather than a current team preference', function () {
    $actor = User::factory()->create();
    $personal = $actor->personalTeam();
    $team = User::factory()->create()->personalTeam();
    $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    $team->fresh()->update(['reporting_currency_code' => 'INR']);
    consolidatedObservation($personal, 'USD', 'checking', '999999');
    consolidatedObservation($team, 'USD', 'checking', '10');
    ExchangeRate::factory()->create(['team_id' => $personal->id, 'rate' => '1000', 'effective_at' => '2026-10-20T00:00:00Z']);
    ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => '80', 'effective_at' => '2026-10-20T00:00:00Z']);

    $result = app(ConsolidatedNetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect($result->reportingCurrencyCode)->toBe('INR');
    expect($result->netWorth)->toBe('800.00000000');
    expect($result->original->teamId)->toBe($team->id);
    expect($actor->fresh()->current_team_id)->toBe($personal->id);
});

test('another teams rates cannot fill missing FX in an otherwise authorized household', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    consolidatedObservation($team, 'INR', 'savings', '800');
    ExchangeRate::factory()->create(['effective_at' => '2026-10-20T00:00:00Z']);

    $result = app(ConsolidatedNetWorthCalculator::class)->handle($actor, $team, '2026-10-20');

    expect($result->isComplete)->toBeFalse();
    expect($result->missingRateCurrencies)->toBe(['INR']);
    expect($result->netWorth)->toBeNull();
});

test('unauthorized callers cannot obtain consolidated results', function () {
    $actor = User::factory()->create();
    $other = User::factory()->create()->personalTeam();

    expect(fn () => app(ConsolidatedNetWorthCalculator::class)->handle($actor, $other, '2026-10-20'))->toThrow(AuthorizationException::class);
});

test('consolidation selects account balances once and resolves one quote per currency without writes', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    foreach (range(1, 5) as $index) {
        consolidatedObservation($team, 'INR', 'savings', '80');
    }
    consolidatedObservation($team, 'USD', 'checking', '10');
    ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => '80', 'effective_at' => '2026-10-20T00:00:00Z']);
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $result = app(ConsolidatedNetWorthCalculator::class)->handle($actor, $team, '2026-10-20');
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($result->netWorth)->toBe('15.00000000');
    expect(array_all($queries, fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select')))->toBeTrue();
    expect(array_values(array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'account_balances'))))->toHaveCount(1);
    expect(array_values(array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'exchange_rates'))))->toHaveCount(1);
    $json = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true);
    expect($json['netWorth'])->toBe('15.00000000');
    expect($json['fxQuotes']['INR']['rate'])->toBe('80.000000000000000000');
});
