<?php

use App\Actions\Finance\CreateTransfer;
use App\Actions\Finance\FinancialActivityCalculator;
use App\Actions\Finance\ReverseTransaction;
use App\Actions\Finance\ReverseTransfer;
use App\Data\CurrencyFinancialActivity;
use App\Data\FinancialActivityResult;
use App\Data\FinancialActivityTotals;
use App\Enums\FinancialAccountType;
use App\Enums\TeamRole;
use App\Enums\TransactionCategoryKind;
use App\Enums\TransactionKind;
use App\Enums\TransactionStatus;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionCategory;
use App\Models\TransactionRevision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @param array<string, mixed> $attributes */
function activityObservation(FinancialAccount $account, string $amount, TransactionKind $kind = TransactionKind::Expense, array $attributes = []): AccountTransaction
{
    return AccountTransaction::factory()->posted()->create([
        'financial_account_id' => $account->id, 'amount' => $amount, 'kind' => $kind,
        'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-02', ...$attributes,
    ]);
}

test('recorded spending income refunds and clawbacks respect every asset and liability account type', function (FinancialAccountType $type, string $purchase, string $income, string $refund, string $clawback) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id, 'type' => $type]);
    activityObservation($account, $purchase);
    activityObservation($account, $income, TransactionKind::Income);
    activityObservation($account, $refund);
    activityObservation($account, $clawback, TransactionKind::Income);

    $result = app(FinancialActivityCalculator::class)->handle($actor, $team, '2026-10-01', '2026-10-31');

    expect($result)->toBeInstanceOf(FinancialActivityResult::class);
    expect($result->dateBasis)->toBe('posted_date');
    $currency = $result->currencies['USD'];
    expect($currency)->toBeInstanceOf(CurrencyFinancialActivity::class);
    expect($currency->totals)->toBeInstanceOf(FinancialActivityTotals::class);
    expect($currency->totals->recordedIncome)->toBe('990.00000000');
    expect($currency->totals->recordedExpenses)->toBe('75.00000000');
    expect($currency->totals->netRecordedIncome)->toBe('915.00000000');
    expect($currency->totals->transactionCount)->toBe(4);
    expect($currency->byAccount[0]['accountType'])->toBe($type);
    expect($currency->byAccount[0]['totals'])->toEqual($currency->totals);
    expect($currency->byCategory[0]['categoryId'])->toBeNull();
    expect($currency->byCategory[0]['totals'])->toEqual($currency->totals);
    expect($currency->monthly[0]['month'])->toBe('2026-10');
    expect($currency->monthly[0]['totals'])->toEqual($currency->totals);
})->with([
    'checking' => [FinancialAccountType::Checking, '-100', '1000', '25', '-10'],
    'savings' => [FinancialAccountType::Savings, '-100', '1000', '25', '-10'],
    'brokerage' => [FinancialAccountType::Brokerage, '-100', '1000', '25', '-10'],
    'other asset' => [FinancialAccountType::OtherAsset, '-100', '1000', '25', '-10'],
    'credit card' => [FinancialAccountType::CreditCard, '100', '-1000', '-25', '10'],
    'loan' => [FinancialAccountType::Loan, '100', '-1000', '-25', '10'],
    'other liability' => [FinancialAccountType::OtherLiability, '100', '-1000', '-25', '10'],
]);

test('currencies stay separate with exact category account and net totals', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $usd = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $card = FinancialAccount::factory()->create(['team_id' => $team->id, 'type' => FinancialAccountType::CreditCard]);
    $inr = FinancialAccount::factory()->create(['team_id' => $team->id, 'currency_code' => 'INR']);
    activityObservation($usd, '3000', TransactionKind::Income);
    activityObservation($usd, '-350.25');
    activityObservation($card, '100');
    activityObservation($inr, '100000', TransactionKind::Income);
    activityObservation($inr, '-30000.125');

    $result = app(FinancialActivityCalculator::class)->handle($actor, $team, '2026-10-01', '2026-10-31');

    expect(array_keys($result->currencies))->toBe(['INR', 'USD']);
    expect($result->currencies['USD']->totals->recordedIncome)->toBe('3000.00000000');
    expect($result->currencies['USD']->totals->recordedExpenses)->toBe('450.25000000');
    expect($result->currencies['USD']->totals->netRecordedIncome)->toBe('2549.75000000');
    expect($result->currencies['INR']->totals->recordedIncome)->toBe('100000.00000000');
    expect($result->currencies['INR']->totals->recordedExpenses)->toBe('30000.12500000');
    expect($result->currencies['INR']->totals->netRecordedIncome)->toBe('69999.87500000');
    expect($result->currencies['USD']->byAccount)->toHaveCount(2);
    expect($result->currencies['USD']->byAccount[0]['totals']->netRecordedIncome)->toBe('2649.75000000');
    expect($result->currencies['USD']->byAccount[1]['totals']->netRecordedIncome)->toBe('-100.00000000');
    expect(array_keys($result->jsonSerialize()))->not->toContain('totals', 'netWorth', 'isComplete');
});

test('direct category buckets retain archived hierarchy without parent child double counting', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $root = TransactionCategory::factory()->create(['team_id' => $team->id, 'name' => 'Home']);
    $child = TransactionCategory::factory()->create(['team_id' => $team->id, 'name' => 'Utilities', 'parent_id' => $root->id]);
    $salary = TransactionCategory::factory()->create(['team_id' => $team->id, 'name' => 'Salary', 'kind' => TransactionCategoryKind::Income]);
    TransactionCategory::factory()->create(['team_id' => $team->id, 'name' => 'Unused']);
    activityObservation($account, '-100', attributes: ['category_id' => $root->id]);
    activityObservation($account, '-50', attributes: ['category_id' => $child->id]);
    activityObservation($account, '-20');
    activityObservation($account, '1000', TransactionKind::Income, ['category_id' => $salary->id]);
    $account->update(['archived_at' => now()]);
    $root->update(['archived_at' => now()]);
    $child->update(['archived_at' => now()]);

    $result = app(FinancialActivityCalculator::class)->handle($actor, $team, '2026-10-01', '2026-10-31')->currencies['USD'];

    expect($result->totals->recordedIncome)->toBe('1000.00000000');
    expect($result->totals->recordedExpenses)->toBe('170.00000000');
    expect($result->totals->netRecordedIncome)->toBe('830.00000000');
    expect(array_column($result->byCategory, 'categoryId'))->toBe([null, $root->id, $child->id, $salary->id]);
    expect($result->byCategory[0]['categoryName'])->toBe('Uncategorized');
    expect($result->byCategory[1]['totals']->recordedExpenses)->toBe('100.00000000');
    expect($result->byCategory[2]['totals']->recordedExpenses)->toBe('50.00000000');
    expect($result->byCategory[2]['parentCategoryId'])->toBe($root->id);
    expect($result->byCategory[2]['parentCategoryName'])->toBe('Home');
    expect($result->byCategory[2]['archivedAt'])->not->toBeNull();
    expect($result->byAccount[0]['archivedAt'])->not->toBeNull();
});

test('posted dates define inclusive boundaries and zero filled partial monthly trends', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    foreach ([['2026-01-14', '999', TransactionKind::Income], ['2026-01-15', '100', TransactionKind::Income],
        ['2026-01-31', '-20', TransactionKind::Expense], ['2026-03-01', '200', TransactionKind::Income],
        ['2026-03-20', '-50', TransactionKind::Expense], ['2026-03-21', '-999', TransactionKind::Expense]] as [$posted, $amount, $kind]) {
        activityObservation($account, $amount, $kind, ['posted_date' => $posted, 'transaction_date' => '2025-01-01', 'recorded_at' => '2027-01-01T12:00:00Z']);
    }

    $result = app(FinancialActivityCalculator::class)->handle($actor, $team, '2026-01-15', '2026-03-20')->currencies['USD'];

    expect($result->totals->recordedIncome)->toBe('300.00000000');
    expect($result->totals->recordedExpenses)->toBe('70.00000000');
    expect($result->totals->transactionCount)->toBe(4);
    expect(array_column($result->monthly, 'month'))->toBe(['2026-01', '2026-02', '2026-03']);
    expect($result->monthly[0]['totals']->netRecordedIncome)->toBe('80.00000000');
    expect($result->monthly[1]['totals']->recordedIncome)->toBe('0.00000000');
    expect($result->monthly[1]['totals']->recordedExpenses)->toBe('0.00000000');
    expect($result->monthly[1]['totals']->transactionCount)->toBe(0);
    expect($result->monthly[2]['totals']->netRecordedIncome)->toBe('150.00000000');
});

test('a single date or leap day includes its entire posted date', function (string $date) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    activityObservation($account, '-0.12345678', attributes: ['posted_date' => $date]);
    $result = app(FinancialActivityCalculator::class)->handle($actor, $team, $date, $date);
    expect($result->currencies['USD']->totals->recordedExpenses)->toBe('0.12345678');
    expect($result->currencies['USD']->totals->transactionCount)->toBe(1);
})->with(['2026-10-31', '2024-02-29']);

test('excluded statuses classifications and analytics flags do not affect recorded totals', function (array $attributes) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    activityObservation($account, '100', TransactionKind::Income);
    activityObservation($account, '-20');
    activityObservation($account, '-999', attributes: $attributes);

    $result = app(FinancialActivityCalculator::class)->handle($actor, $team, '2026-10-01', '2026-10-31')->currencies['USD'];

    expect($result->totals->recordedIncome)->toBe('100.00000000');
    expect($result->totals->recordedExpenses)->toBe('20.00000000');
    expect($result->totals->transactionCount)->toBe(2);
})->with([
    'pending' => [['status' => TransactionStatus::Pending]],
    'cancelled' => [['status' => TransactionStatus::Cancelled]],
    'transfer without exclusion flag' => [['kind' => TransactionKind::Transfer]],
    'adjustment' => [['kind' => TransactionKind::Adjustment]],
    'unclassified' => [['kind' => TransactionKind::Unclassified]],
    'excluded expense' => [['excluded_from_analytics' => true]],
    'excluded income' => [['kind' => TransactionKind::Income, 'excluded_from_analytics' => true]],
]);

test('credit card purchases count as spending while household payments transfers and their reversals do not', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $checking = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $card = FinancialAccount::factory()->create(['team_id' => $team->id, 'type' => FinancialAccountType::CreditCard]);
    $inr = FinancialAccount::factory()->create(['team_id' => $team->id, 'currency_code' => 'INR']);
    activityObservation($checking, '2000', TransactionKind::Income);
    activityObservation($card, '150');
    $input = ['source_account_id' => $checking->id, 'destination_account_id' => $card->id, 'source_amount' => '100', 'destination_amount' => '100', 'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-02'];
    $payment = app(CreateTransfer::class)->handle($actor, $team, $input);
    app(ReverseTransfer::class)->handle($actor, $team, $payment, ['expected_source_revision' => 1, 'expected_destination_revision' => 1, 'posted_date' => '2026-10-03', 'reason' => 'Wrong payment']);
    app(CreateTransfer::class)->handle($actor, $team, [...$input, 'destination_account_id' => $inr->id, 'destination_amount' => '8300']);

    $result = app(FinancialActivityCalculator::class)->handle($actor, $team, '2026-10-01', '2026-10-31');

    expect(array_keys($result->currencies))->toBe(['USD']);
    expect($result->currencies['USD']->totals->recordedIncome)->toBe('2000.00000000');
    expect($result->currencies['USD']->totals->recordedExpenses)->toBe('150.00000000');
    expect($result->currencies['USD']->totals->transactionCount)->toBe(2);
});

test('standalone income and expense reversals offset in their own posting periods without clamping', function (FinancialAccountType $expenseType, string $expenseAmount) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $checking = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $expenseAccount = FinancialAccount::factory()->create(['team_id' => $team->id, 'type' => $expenseType]);
    $income = activityObservation($checking, '1000', TransactionKind::Income, ['posted_date' => '2026-01-31']);
    $expense = activityObservation($expenseAccount, $expenseAmount, attributes: ['posted_date' => '2026-01-31']);
    foreach ([$income, $expense] as $original) {
        app(ReverseTransaction::class)->handle($actor, $team, $original, ['expected_revision' => 1, 'posted_date' => '2026-02-02', 'reason' => 'Reversal in next period']);
    }
    $calculator = app(FinancialActivityCalculator::class);
    $both = $calculator->handle($actor, $team, '2026-01-01', '2026-02-28')->currencies['USD'];
    $february = $calculator->handle($actor, $team, '2026-02-01', '2026-02-28')->currencies['USD'];

    expect($both->totals->recordedIncome)->toBe('0.00000000');
    expect($both->totals->recordedExpenses)->toBe('0.00000000');
    expect($both->totals->transactionCount)->toBe(4);
    expect($both->monthly[0]['totals']->netRecordedIncome)->toBe('900.00000000');
    expect($both->monthly[1]['totals']->netRecordedIncome)->toBe('-900.00000000');
    expect($february->totals->recordedIncome)->toBe('-1000.00000000');
    expect($february->totals->recordedExpenses)->toBe('-100.00000000');
    expect($february->totals->netRecordedIncome)->toBe('-900.00000000');
})->with([[FinancialAccountType::Checking, '-100'], [FinancialAccountType::CreditCard, '100']]);

test('refunds exceeding recorded purchases remain negative spending rather than income', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id, 'type' => FinancialAccountType::CreditCard]);
    activityObservation($account, '10');
    activityObservation($account, '-25');
    $totals = app(FinancialActivityCalculator::class)->handle($actor, $team, '2026-10-01', '2026-10-31')->currencies['USD']->totals;
    expect($totals->recordedIncome)->toBe('0.00000000');
    expect($totals->recordedExpenses)->toBe('-15.00000000');
    expect($totals->netRecordedIncome)->toBe('15.00000000');
});

test('exact totals exceed individual storage precision and serialize as decimal strings deterministically', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    activityObservation($account, '9999999999999999.00000001', TransactionKind::Income);
    activityObservation($account, '9999999999999999.00000001', TransactionKind::Income);
    foreach (range(1, 3) as $index) {
        activityObservation($account, '-0.00000001');
    }
    $calculator = app(FinancialActivityCalculator::class);
    $first = json_encode($calculator->handle($actor, $team, '2026-10-01', '2026-10-31'), JSON_THROW_ON_ERROR);
    $second = json_encode($calculator->handle($actor, $team, '2026-10-01', '2026-10-31'), JSON_THROW_ON_ERROR);
    $json = json_decode($first, true, flags: JSON_THROW_ON_ERROR);

    expect($second)->toBe($first);
    expect($json['currencies']['USD']['totals']['recordedIncome'])->toBe('19999999999999998.00000002');
    expect($json['currencies']['USD']['totals']['recordedExpenses'])->toBe('0.00000003');
    expect($json['currencies']['USD']['totals']['netRecordedIncome'])->toBe('19999999999999997.99999999');
    expect($json['currencies']['USD']['byAccount'][0]['accountType'])->toBe('checking');
    expect($json['currencies']['USD']['monthly'][0]['totals'])->toBe($json['currencies']['USD']['totals']);
});

test('empty and wholly excluded reports declare recorded coverage without inventing currency totals', function (bool $withExcluded) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    if ($withExcluded) {
        $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
        activityObservation($account, '-100', attributes: ['excluded_from_analytics' => true]);
    }
    $result = app(FinancialActivityCalculator::class)->handle($actor, $team, '2026-10-01', '2026-10-31');
    expect($result->currencies)->toBe([]);
    expect($result->label)->toBe('Recorded income and spending');
    expect($result->coverageNote)->toContain('coverage may be incomplete', 'not necessarily direct cash movements');
    expect(json_encode($result, JSON_THROW_ON_ERROR))->toContain('"currencies":{}');
})->with([false, true]);

test('household readers can calculate reports while outsiders cannot', function (?TeamRole $role, bool $allowed) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    activityObservation($account, '-100');
    $actor = User::factory()->create();
    if ($role !== null) {
        $team->members()->attach($actor, ['role' => $role->value]);
    }
    if ($allowed) {
        expect(app(FinancialActivityCalculator::class)->handle($actor, $team, '2026-10-01', '2026-10-31')->currencies['USD']->totals->recordedExpenses)->toBe('100.00000000');
    } else {
        expect(fn () => app(FinancialActivityCalculator::class)->handle($actor, $team, '2026-10-01', '2026-10-31'))->toThrow(AuthorizationException::class);
    }
})->with([[TeamRole::Owner, true], [TeamRole::Admin, true], [TeamRole::Member, true], [null, false]]);

test('explicit teams isolate identical categories and accounts regardless of current team preference', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $other = Team::factory()->create();
    $other->members()->attach($actor, ['role' => TeamRole::Owner->value]);
    $category = TransactionCategory::factory()->create(['team_id' => $team->id, 'name' => 'Shopping']);
    $foreignCategory = TransactionCategory::factory()->create(['team_id' => $other->id, 'name' => 'Shopping']);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id, 'name' => 'Checking']);
    $foreignAccount = FinancialAccount::factory()->create(['team_id' => $other->id, 'name' => 'Checking']);
    activityObservation($account, '-10', attributes: ['category_id' => $category->id]);
    activityObservation($foreignAccount, '-999', attributes: ['category_id' => $foreignCategory->id]);
    $actor->switchTeam($other);

    $result = app(FinancialActivityCalculator::class)->handle($actor, $team, '2026-10-01', '2026-10-31');
    expect($result->teamId)->toBe($team->id);
    expect($result->currencies['USD']->totals->recordedExpenses)->toBe('10.00000000');
    expect(array_column($result->currencies['USD']->byCategory, 'categoryId'))->toBe([$category->id]);
    expect(array_column($result->currencies['USD']->byAccount, 'accountId'))->toBe([$account->id]);
});

test('invalid reversed or ambiguous date ranges fail explicitly', function (string $start, string $end) {
    $actor = User::factory()->create();
    expect(fn () => app(FinancialActivityCalculator::class)->handle($actor, $actor->personalTeam(), $start, $end))->toThrow(ValidationException::class);
})->with([['2026-10-31', '2026-10-01'], ['2026-02-30', '2026-03-01'], ['10/01/2026', '2026-10-31'], ['', '2026-10-31']]);

test('missing and deleted teams cannot be calculated from stale model instances', function (bool $deleted) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    if ($deleted) {
        $team->delete();
    } else {
        $team = new Team(['name' => 'Missing']);
    }
    expect(fn () => app(FinancialActivityCalculator::class)->handle($actor, $team, '2026-10-01', '2026-10-31'))->toThrow(ModelNotFoundException::class);
})->with([false, true]);

test('one joined activity query handles many categories without reading audits or changing balances', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    foreach (range(1, 30) as $index) {
        $category = TransactionCategory::factory()->create(['team_id' => $team->id]);
        activityObservation($account, '-0.10', attributes: ['category_id' => $category->id]);
    }
    $beforeBalance = $balance->fresh()->getAttributes();
    $beforeTransactions = AccountTransaction::orderBy('id')->get()->toArray();
    $beforeRevisions = TransactionRevision::orderBy('id')->get()->toArray();
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $result = app(FinancialActivityCalculator::class)->handle($actor, $team, '2026-10-01', '2026-10-31');
        $queries = array_column(DB::getQueryLog(), 'query');
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($result->currencies['USD']->totals->recordedExpenses)->toBe('3.00000000');
    expect($result->currencies['USD']->byCategory)->toHaveCount(30);
    expect(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'account_transactions')
        || str_contains($sql, 'financial_accounts') || str_contains($sql, 'transaction_categories')))->toHaveCount(1);
    foreach ($queries as $sql) {
        expect(strtolower($sql))->not->toContain('transaction_revisions', 'account_balances', 'for update');
        expect(preg_match('/^\s*(insert|update|delete|alter|create|drop)\b/i', $sql))->toBe(0);
    }
    expect($balance->fresh()->getAttributes())->toBe($beforeBalance);
    expect(AccountTransaction::orderBy('id')->get()->toArray())->toBe($beforeTransactions);
    expect(TransactionRevision::orderBy('id')->get()->toArray())->toBe($beforeRevisions);
});
