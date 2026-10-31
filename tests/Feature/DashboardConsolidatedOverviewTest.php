<?php

use App\Enums\TeamRole;
use App\Models\AccountBalance;
use App\Models\ExchangeRate;
use App\Models\FinancialAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboards consolidate USD and INR using the teams reporting currency and preserve original figures', function (string $reporting, string $assets, string $liabilities, string $netWorth) {
    $this->travelTo(CarbonImmutable::parse('2026-10-25T12:00:00Z'));
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $team->update(['reporting_currency_code' => $reporting]);
    foreach ([['USD', 'checking', '100'], ['USD', 'loan', '20'], ['INR', 'savings', '8000'], ['INR', 'credit_card', '1600']] as [$currency, $type, $amount]) {
        $account = FinancialAccount::factory()->create(['team_id' => $team->id, 'currency_code' => $currency, 'type' => $type]);
        AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => $amount, 'as_of_date' => '2026-10-24']);
    }
    $rate = ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => '80', 'effective_at' => '2026-10-20T00:00:00Z']);

    $this->actingAs($actor)->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('dashboard')
        ->where('consolidatedOverview.isComplete', true)->where('consolidatedOverview.reportingCurrencyCode', $reporting)
        ->where('consolidatedOverview.totalAssets', $assets)->where('consolidatedOverview.totalLiabilities', $liabilities)
        ->where('consolidatedOverview.netWorth', $netWorth)->where('consolidatedOverview.original.asOfDate', '2026-10-25')
        ->where('financialOverview.currencies.USD.netWorth', '80.00000000')->where('financialOverview.currencies.INR.netWorth', '6400.00000000')
        ->where('consolidatedOverview.fxQuotes.'.($reporting === 'USD' ? 'INR' : 'USD').'.rateId', $rate->id)
        ->where('consolidatedOverview.fxQuotes.'.($reporting === 'USD' ? 'INR' : 'USD').'.source', 'manual')
        ->where('consolidatedOverview.fxQuotes.'.($reporting === 'USD' ? 'INR' : 'USD').'.effectiveAt', '2026-10-20T00:00:00.000000Z')
        ->where('canManageExchangeRates', true));
})->with([['USD', '200.00000000', '40.00000000', '160.00000000'], ['INR', '16000.00000000', '3200.00000000', '12800.00000000']]);

test('incomplete dashboard valuations identify missing balances and missing FX independently', function (bool $missingBalance, bool $missingFx) {
    $this->travelTo(CarbonImmutable::parse('2026-10-25T12:00:00Z'));
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $usd = FinancialAccount::factory()->create(['team_id' => $team->id]);
    AccountBalance::factory()->create(['financial_account_id' => $usd->id, 'amount' => '100', 'as_of_date' => '2026-10-24']);
    $inr = FinancialAccount::factory()->create(['team_id' => $team->id, 'currency_code' => 'INR']);
    AccountBalance::factory()->create(['financial_account_id' => $inr->id, 'amount' => '800', 'as_of_date' => '2026-10-24']);
    if ($missingBalance) {
        FinancialAccount::factory()->create(['team_id' => $team->id, 'type' => 'loan']);
    }
    if (! $missingFx) {
        ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => '80', 'effective_at' => '2026-10-20T00:00:00Z']);
    }

    $this->actingAs($actor)->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('consolidatedOverview.isComplete', false)
        ->where('consolidatedOverview.totalAssets', null)->where('consolidatedOverview.totalLiabilities', null)
        ->where('consolidatedOverview.netWorth', null)
        ->where('consolidatedOverview.missingRateCurrencies', $missingFx ? ['INR'] : [])
        ->where('financialOverview.isComplete', ! $missingBalance)
        ->where('financialOverview.currencies.USD.totalAssets', '100.00000000')
        ->where('financialOverview.currencies.INR.totalAssets', '800.00000000'));
})->with(['balances only' => [true, false], 'FX only' => [false, true], 'balances and FX' => [true, true]]);

test('empty dashboard households keep reporting currency metadata without requiring FX', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();

    $this->actingAs($actor)->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('financialOverview.currencies', 0)
        ->where('consolidatedOverview.reportingCurrencyCode', 'USD')->where('consolidatedOverview.isComplete', true)
        ->has('consolidatedOverview.fxQuotes', 0)->where('consolidatedOverview.missingRateCurrencies', []));
});

test('consolidated dashboard calculations never borrow another teams rates', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-25T12:00:00Z'));
    $actor = User::factory()->create();
    $personal = $actor->personalTeam();
    $team = User::factory()->create()->personalTeam();
    $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id, 'currency_code' => 'INR']);
    AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '800', 'as_of_date' => '2026-10-24']);
    ExchangeRate::factory()->create(['team_id' => $personal->id, 'rate' => '80', 'effective_at' => '2026-10-20T00:00:00Z']);

    $this->actingAs($actor)->get(route('dashboard', ['current_team' => $team->slug, 'team_id' => $personal->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('consolidatedOverview.original.teamId', $team->id)
        ->where('consolidatedOverview.netWorth', null)->where('consolidatedOverview.missingRateCurrencies', ['INR'])
        ->where('canManageExchangeRates', false)->where('financialOverview.currencies.INR.netWorth', '800.00000000'));
});

test('dashboard aliases share one calculation and one rate lookup per currency', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-25T12:00:00Z'));
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id, 'currency_code' => 'INR']);
    AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '800', 'as_of_date' => '2026-10-24']);
    ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => '80', 'effective_at' => '2026-10-20T00:00:00Z']);
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $response = $this->actingAs($actor)->get(route('dashboard', ['current_team' => $team->slug]));
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('consolidatedOverview.netWorth', '10.00000000')->where('financialOverview.currencies.INR.netWorth', '800.00000000'));
    expect(array_values(array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'account_balances'))))->toHaveCount(1);
    expect(array_values(array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'exchange_rates'))))->toHaveCount(1);
});
