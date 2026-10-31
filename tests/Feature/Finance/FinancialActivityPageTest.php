<?php

use App\Actions\Finance\FinancialActivityCalculator;
use App\Actions\Finance\ReverseTransaction;
use App\Enums\FinancialAccountType;
use App\Enums\TeamRole;
use App\Enums\TransactionCategoryKind;
use App\Enums\TransactionKind;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionCategory;
use App\Models\TransactionRevision;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/** @param array<string, mixed> $attributes */
function activityPageTransaction(FinancialAccount $account, string $amount, TransactionKind $kind = TransactionKind::Expense, array $attributes = []): AccountTransaction
{
    return AccountTransaction::factory()->posted()->create([
        'financial_account_id' => $account->id, 'amount' => $amount, 'kind' => $kind,
        'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-20', ...$attributes,
    ]);
}

test('activity defaults use twelve calendar months and server generated calendar presets', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-26T12:00:00Z'));
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $this->actingAs($actor)->get(route('financial-activity.index', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('financial-activity/index')
        ->where('team.id', $team->id)->where('filters.start_date', '2025-11-01')->where('filters.end_date', '2026-10-31')
        ->where('filters.currency', 'USD')->where('activity.startDate', '2025-11-01')->where('activity.endDate', '2026-10-31')
        ->where('activity.dateBasis', 'posted_date')->where('activity.label', 'Recorded income and spending')
        ->where('activity.currencies', [])->where('canCreateTransaction', true)
        ->where('presets', [
            ['months' => 1, 'startDate' => '2026-10-01', 'endDate' => '2026-10-31'],
            ['months' => 3, 'startDate' => '2026-08-01', 'endDate' => '2026-10-31'],
            ['months' => 6, 'startDate' => '2026-05-01', 'endDate' => '2026-10-31'],
            ['months' => 12, 'startDate' => '2025-11-01', 'endDate' => '2026-10-31'],
        ]));
});

test('calendar defaults handle leap years and year boundaries', function (string $now, string $start, string $end) {
    $this->travelTo(CarbonImmutable::parse($now));
    $actor = User::factory()->create();
    $this->actingAs($actor)->get(route('financial-activity.index', ['current_team' => $actor->personalTeam()->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.start_date', $start)->where('filters.end_date', $end));
})->with([
    ['2024-02-10T12:00:00Z', '2023-03-01', '2024-02-29'],
    ['2026-01-10T12:00:00Z', '2025-02-01', '2026-01-31'],
]);

test('household owners admins and members can read activity with appropriate creation permissions', function (?TeamRole $role, bool $read, bool $create) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $actor = User::factory()->create();
    if ($role !== null) {
        $team->members()->attach($actor, ['role' => $role->value]);
    }
    $response = $this->actingAs($actor)->get(route('financial-activity.index', ['current_team' => $team->slug]));
    if ($read) {
        $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('canCreateTransaction', $create));
    } else {
        $response->assertForbidden();
    }
})->with([[TeamRole::Owner, true, true], [TeamRole::Admin, true, true], [TeamRole::Member, true, false], [null, false, false]]);

test('guests cannot access household activity', function () {
    $owner = User::factory()->create();
    $this->get(route('financial-activity.index', ['current_team' => $owner->personalTeam()->slug]))->assertRedirect(route('login'));
});

test('single currency households choose their available native currency and serialize exact recorded totals', function (string $currency) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id, 'currency_code' => $currency]);
    activityPageTransaction($account, '100000.00000001', TransactionKind::Income);
    activityPageTransaction($account, '-275.12345678');
    $this->actingAs($actor)->get(route('financial-activity.index', [
        'current_team' => $team->slug, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31',
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.currency', $currency)
        ->has('activity.currencies', 1)->where('activity.currencies.'.$currency.'.totals', [
            'recordedIncome' => '100000.00000001', 'recordedExpenses' => '275.12345678',
            'netRecordedIncome' => '99724.87654323', 'transactionCount' => 2,
        ])->where('activity.currencies.'.$currency.'.byAccount.0.accountId', $account->id));
})->with(['USD', 'INR']);

test('mixed currency requests preserve the calculator DTO without a combined monetary total', function (string $selected) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $usd = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $inr = FinancialAccount::factory()->create(['team_id' => $team->id, 'currency_code' => 'INR']);
    activityPageTransaction($usd, '9999999999999999.12345678', TransactionKind::Income);
    activityPageTransaction($inr, '-30000.12345678');
    $expected = json_decode(json_encode(app(FinancialActivityCalculator::class)->handle($actor, $team, '2026-10-01', '2026-10-31'), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

    $this->actingAs($actor)->get(route('financial-activity.index', [
        'current_team' => $team->slug, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'currency' => $selected,
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.currency', $selected)->where('activity', $expected)
        ->where('activity.currencies.USD.totals.recordedIncome', '9999999999999999.12345678')
        ->where('activity.currencies.INR.totals.recordedExpenses', '30000.12345678')
        ->missing('activity.totals')->missing('activity.isComplete'));
})->with(['USD', 'INR']);

test('date filtering uses inclusive posting dates rather than transaction or recording dates', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    activityPageTransaction($account, '500', TransactionKind::Income, ['transaction_date' => '2026-09-01', 'posted_date' => '2026-10-01', 'recorded_at' => '2027-01-01T12:00:00Z']);
    activityPageTransaction($account, '-100', attributes: ['posted_date' => '2026-10-31']);
    activityPageTransaction($account, '9999', TransactionKind::Income, ['posted_date' => '2026-09-30']);
    activityPageTransaction($account, '-9999', attributes: ['posted_date' => '2026-11-01']);

    $this->actingAs($actor)->get(route('financial-activity.index', [
        'current_team' => $team->slug, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31',
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('activity.currencies.USD.totals.recordedIncome', '500.00000000')
        ->where('activity.currencies.USD.totals.recordedExpenses', '100.00000000')
        ->where('activity.currencies.USD.totals.transactionCount', 2)->has('activity.currencies.USD.monthly', 1));
});

test('empty histories and currencies without qualifying activity retain unknown coverage', function (bool $otherCurrencyActivity) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    if ($otherCurrencyActivity) {
        $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
        activityPageTransaction($account, '-10');
    }
    $response = $this->actingAs($actor)->get(route('financial-activity.index', [
        'current_team' => $team->slug, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'currency' => 'INR',
    ]));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.currency', 'INR')->missing('activity.currencies.INR')
        ->where('activity.coverageNote', fn (string $note): bool => str_contains($note, 'coverage may be incomplete')));
    $this->assertDatabaseCount('account_transactions', $otherCurrencyActivity ? 1 : 0);
})->with([false, true]);

test('reversal periods and archived direct category account breakdowns reach React with negative amounts intact', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $checking = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $card = FinancialAccount::factory()->create(['team_id' => $team->id, 'name' => 'Family card', 'type' => FinancialAccountType::CreditCard]);
    $incomeCategory = TransactionCategory::factory()->create(['team_id' => $team->id, 'name' => 'Salary', 'kind' => TransactionCategoryKind::Income]);
    $parent = TransactionCategory::factory()->create(['team_id' => $team->id, 'name' => 'Food']);
    $child = TransactionCategory::factory()->create(['team_id' => $team->id, 'name' => 'Groceries', 'parent_id' => $parent->id]);
    $income = activityPageTransaction($checking, '1000', TransactionKind::Income, ['category_id' => $incomeCategory->id, 'posted_date' => '2026-01-31']);
    $purchase = activityPageTransaction($card, '100', attributes: ['category_id' => $child->id, 'posted_date' => '2026-01-31']);
    foreach ([$income, $purchase] as $original) {
        app(ReverseTransaction::class)->handle($actor, $team, $original, ['expected_revision' => 1, 'posted_date' => '2026-02-02', 'reason' => 'February correction']);
    }
    $card->update(['archived_at' => now()]);
    $child->update(['archived_at' => now()]);
    $this->actingAs($actor)->get(route('financial-activity.index', [
        'current_team' => $team->slug, 'start_date' => '2026-02-01', 'end_date' => '2026-03-31',
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('activity.currencies.USD.totals.recordedIncome', '-1000.00000000')
        ->where('activity.currencies.USD.totals.recordedExpenses', '-100.00000000')
        ->where('activity.currencies.USD.totals.netRecordedIncome', '-900.00000000')
        ->has('activity.currencies.USD.byCategory', 2)->where('activity.currencies.USD.byCategory.1.categoryId', $child->id)
        ->where('activity.currencies.USD.byCategory.1.categoryName', 'Groceries')->where('activity.currencies.USD.byCategory.1.parentCategoryName', 'Food')
        ->where('activity.currencies.USD.byCategory.1.archivedAt', fn ($date) => $date !== null)
        ->where('activity.currencies.USD.byAccount.1.accountName', 'Family card')->where('activity.currencies.USD.byAccount.1.accountType', 'credit_card')
        ->where('activity.currencies.USD.byAccount.1.archivedAt', fn ($date) => $date !== null)
        ->where('activity.currencies.USD.monthly.0.month', '2026-02')->where('activity.currencies.USD.monthly.0.totals.transactionCount', 2)
        ->where('activity.currencies.USD.monthly.1.month', '2026-03')->where('activity.currencies.USD.monthly.1.totals.transactionCount', 0)
        ->where('activity.currencies.USD.monthly.1.totals.recordedExpenses', '0.00000000'));
});

test('native route teams isolate reports even when current team preference differs', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $other = Team::factory()->create();
    $other->members()->attach($actor, ['role' => TeamRole::Owner->value]);
    $ownAccount = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $otherAccount = FinancialAccount::factory()->create(['team_id' => $other->id]);
    activityPageTransaction($ownAccount, '-10');
    activityPageTransaction($otherAccount, '-999');
    $actor->switchTeam($other);
    $this->actingAs($actor)->get(route('financial-activity.index', [
        'current_team' => $team->slug, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31',
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('team.id', $team->id)->where('activity.teamId', $team->id)
        ->where('activity.currencies.USD.totals.recordedExpenses', '10.00000000')->where('activity.currencies.USD.byAccount.0.accountId', $ownAccount->id));
});

test('zero net reversal months remain recorded activity rather than missing history', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $original = activityPageTransaction($account, '-100');
    app(ReverseTransaction::class)->handle($actor, $team, $original, ['expected_revision' => 1, 'posted_date' => '2026-10-21', 'reason' => 'Same month correction']);
    $this->actingAs($actor)->get(route('financial-activity.index', [
        'current_team' => $team->slug, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31',
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.currency', 'USD')
        ->where('activity.currencies.USD.totals.recordedExpenses', '0.00000000')
        ->where('activity.currencies.USD.totals.transactionCount', 2)
        ->where('activity.currencies.USD.monthly.0.totals.transactionCount', 2));
});

test('invalid activity date currency and tenant filters produce validation feedback', function (array $changes, string $field) {
    $actor = User::factory()->create();
    $input = array_replace(['current_team' => $actor->personalTeam()->slug, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31'], $changes);
    $this->actingAs($actor)->getJson(route('financial-activity.index', $input))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'reversed range' => [['start_date' => '2026-11-01'], 'end_date'],
    'invalid date' => [['start_date' => '2026-02-30'], 'start_date'],
    'blank date' => [['start_date' => ''], 'start_date'],
    'empty end' => [['end_date' => ''], 'end_date'],
    'unsupported currency' => [['currency' => 'EUR'], 'currency'],
    'empty currency' => [['currency' => ''], 'currency'],
    'tenant injection' => [['team_id' => 9999], 'team_id'],
]);

test('opening activity is read only and preserves balances transactions and audits', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    activityPageTransaction($account, '-100');
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $balanceBefore = $balance->fresh()->getAttributes();
    $transactionsBefore = AccountTransaction::orderBy('id')->get()->toArray();
    $auditsBefore = TransactionRevision::orderBy('id')->get()->toArray();
    $this->actingAs($actor)->get(route('financial-activity.index', ['current_team' => $team->slug, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']))->assertOk();
    expect($balance->fresh()->getAttributes())->toBe($balanceBefore);
    expect(AccountTransaction::orderBy('id')->get()->toArray())->toBe($transactionsBefore);
    expect(TransactionRevision::orderBy('id')->get()->toArray())->toBe($auditsBefore);
});
