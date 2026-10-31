<?php

use App\Actions\Finance\NetWorthCalculator;
use App\Enums\TeamRole;
use App\Models\AccountBalance;
use App\Models\FinancialAccount;
use App\Models\FinancialInstitution;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/** @param array<string, mixed> $attributes */
function dashboardObservation(Team $team, string $type, string $currency, string $amount, array $attributes = []): AccountBalance
{
    $account = FinancialAccount::factory()->create(['team_id' => $team->id, 'type' => $type, 'currency_code' => $currency, ...$attributes]);

    return AccountBalance::factory()->create([
        'financial_account_id' => $account->id,
        'amount' => $amount,
        'as_of_date' => '2026-10-24',
        'recorded_at' => '2026-10-25 09:00:00.123456',
    ]);
}

test('empty household dashboards receive an empty complete overview for the current date', function () {
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(12, 0));
    $actor = User::factory()->create();
    $team = $actor->personalTeam();

    $this->actingAs($actor)->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('dashboard')
        ->where('team.id', $team->id)->where('team.slug', $team->slug)
        ->where('financialOverview.teamId', $team->id)->where('financialOverview.asOfDate', '2026-10-25')
        ->where('financialOverview.isComplete', true)->has('financialOverview.currencies', 0)
        ->where('canManageAccounts', true)->has('pendingInvitations', 0));
});

test('single currency dashboard data includes exact totals institution type labels and observation metadata', function (string $currency, string $institutionName) {
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(12, 0));
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $institution = FinancialInstitution::factory()->create(['team_id' => $team->id, 'name' => $institutionName]);
    $asset = dashboardObservation($team, 'savings', $currency, '1234567890123456.12345678', ['financial_institution_id' => $institution->id]);
    dashboardObservation($team, 'loan', $currency, '0.00000001');

    $this->actingAs($actor)->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('financialOverview.currencies', 1)
        ->where("financialOverview.currencies.{$currency}.totalAssets", '1234567890123456.12345678')
        ->where("financialOverview.currencies.{$currency}.totalLiabilities", '0.00000001')
        ->where("financialOverview.currencies.{$currency}.netWorth", '1234567890123456.12345677')
        ->where("financialOverview.currencies.{$currency}.assetsByAccountType.savings", '1234567890123456.12345678')
        ->where("financialOverview.currencies.{$currency}.accounts.0.accountId", $asset->financial_account_id)
        ->where("financialOverview.currencies.{$currency}.accounts.0.institutionId", $institution->id)
        ->where("financialOverview.currencies.{$currency}.accounts.0.institutionName", $institutionName)
        ->where("financialOverview.currencies.{$currency}.accounts.0.typeLabel", 'Savings')
        ->where("financialOverview.currencies.{$currency}.accounts.0.isLiability", false)
        ->where("financialOverview.currencies.{$currency}.accounts.0.balanceAsOfDate", '2026-10-24')
        ->where("financialOverview.currencies.{$currency}.accounts.0.balanceRecordedAt", '2026-10-25T09:00:00.123456Z')
        ->where("financialOverview.currencies.{$currency}.accounts.1.isLiability", true)
        ->where("financialOverview.currencies.{$currency}.accounts.1.netWorthContribution", '-0.00000001'));
})->with([['USD', 'Chase'], ['INR', 'ICICI']]);

test('mixed currency dashboard props reuse the calculator serialized contract without a combined total', function () {
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(12, 0));
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    dashboardObservation($team, 'checking', 'USD', '100.10');
    dashboardObservation($team, 'credit_card', 'USD', '20.05');
    dashboardObservation($team, 'brokerage', 'INR', '100000');
    dashboardObservation($team, 'loan', 'INR', '25000');
    $expected = json_decode(json_encode(app(NetWorthCalculator::class)->handle($actor, $team, '2026-10-25'), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

    $this->actingAs($actor)->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('financialOverview', $expected)
        ->where('financialOverview.currencies.USD.netWorth', '80.05000000')
        ->where('financialOverview.currencies.INR.netWorth', '75000.00000000')
        ->missing('financialOverview.netWorth'));
});

test('missing observations remain null and distinct from genuinely recorded zero balances', function () {
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(12, 0));
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    dashboardObservation($team, 'checking', 'USD', '0');
    $missing = FinancialAccount::factory()->create(['team_id' => $team->id, 'type' => 'loan']);

    $this->actingAs($actor)->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('financialOverview.isComplete', false)
        ->where('financialOverview.currencies.USD.isComplete', false)
        ->where('financialOverview.currencies.USD.missingAccountIds', [$missing->id])
        ->where('financialOverview.currencies.USD.accounts.0.balanceAmount', '0.00000000')
        ->where('financialOverview.currencies.USD.accounts.0.missingData', false)
        ->where('financialOverview.currencies.USD.accounts.1.accountId', $missing->id)
        ->where('financialOverview.currencies.USD.accounts.1.balanceAmount', null)
        ->where('financialOverview.currencies.USD.accounts.1.netWorthContribution', null)
        ->where('financialOverview.currencies.USD.accounts.1.balanceAsOfDate', null)
        ->where('financialOverview.currencies.USD.accounts.1.missingData', true));
});

test('archived accounts retain their last known balance and institution on the dashboard', function () {
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(12, 0));
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $institution = FinancialInstitution::factory()->create(['team_id' => $team->id, 'archived_at' => now()]);
    $balance = dashboardObservation($team, 'brokerage', 'USD', '500', [
        'archived_at' => '2026-10-25 11:00:00',
        'financial_institution_id' => $institution->id,
    ]);

    $this->actingAs($actor)->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('financialOverview.currencies.USD.netWorth', '500.00000000')
        ->where('financialOverview.currencies.USD.accounts.0.accountId', $balance->financial_account_id)
        ->where('financialOverview.currencies.USD.accounts.0.archivedAt', '2026-10-25T11:00:00.000000Z')
        ->where('financialOverview.currencies.USD.accounts.0.institutionName', $institution->name));
});

test('the authorized route team controls dashboard calculations rather than preferences or client supplied tenant and date values', function () {
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(12, 0));
    $actor = User::factory()->create();
    $personalTeam = $actor->personalTeam();
    $team = User::factory()->create()->personalTeam();
    $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    dashboardObservation($personalTeam, 'checking', 'USD', '999999');
    $institution = FinancialInstitution::factory()->create(['team_id' => $team->id]);
    dashboardObservation($team, 'savings', 'INR', '500', ['financial_institution_id' => $institution->id]);

    $this->actingAs($actor)->get(route('dashboard', ['current_team' => $team->slug, 'team_id' => $personalTeam->id, 'as_of_date' => '1990-01-01']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('financialOverview.teamId', $team->id)
        ->where('financialOverview.asOfDate', '2026-10-25')
        ->where('team.slug', $team->slug)->where('canManageAccounts', false)
        ->has('financialOverview.currencies', 1)->missing('financialOverview.currencies.USD')
        ->where('financialOverview.currencies.INR.netWorth', '500.00000000')
        ->where('financialOverview.currencies.INR.accounts.0.institutionId', $institution->id));
});

test('dashboard access requires current membership even when current team points to the requested team', function (bool $revoked) {
    $actor = User::factory()->create();
    $team = User::factory()->create()->personalTeam();
    if ($revoked) {
        $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
        $team->members()->detach($actor);
    }
    $actor->update(['current_team_id' => $team->id]);
    dashboardObservation($team, 'checking', 'USD', '100');

    $this->actingAs($actor)->get(route('dashboard', ['current_team' => $team->slug]))->assertForbidden();
})->with([false, true]);

test('retired teams cannot expose dashboard financial data', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    dashboardObservation($team, 'checking', 'USD', '100');
    $slug = $team->slug;
    $team->delete();

    $this->actingAs($actor)->get(route('dashboard', ['current_team' => $slug]))->assertNotFound();
});

test('dashboard institution metadata stays in the same bounded financial query', function () {
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(12, 0));
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $institution = FinancialInstitution::factory()->create(['team_id' => $team->id]);
    foreach (range(1, 5) as $index) {
        dashboardObservation($team, 'checking', 'USD', '100', ['name' => "Account {$index}", 'financial_institution_id' => $institution->id]);
    }
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $response = $this->actingAs($actor)->get(route('dashboard', ['current_team' => $team->slug]));
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->has('financialOverview.currencies.USD.accounts', 5));
    expect(array_values(array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'financial_accounts') || str_contains($query['query'], 'account_balances') || str_contains($query['query'], 'financial_institutions'))))->toHaveCount(1);
});

test('public welcome and authentication pages do not receive household financial data', function (string $routeName) {
    $actor = User::factory()->create();
    dashboardObservation($actor->personalTeam(), 'checking', 'USD', '9999999999.12345678');

    $this->get(route($routeName))->assertOk()->assertInertia(fn (Assert $page) => $page->missing('financialOverview'));
})->with(['home', 'login', 'register']);
