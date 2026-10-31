<?php

namespace App\Actions\Finance;

use App\Data\CurrencyFinancialActivity;
use App\Data\FinancialActivityResult;
use App\Data\FinancialActivityTotals;
use App\Enums\FinancialAccountType;
use App\Enums\TransactionKind;
use App\Enums\TransactionStatus;
use App\Models\AccountTransaction;
use App\Models\Team;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use UnexpectedValueException;

/**
 * A single joined SELECT provides one financial-data snapshot. Exact aggregation
 * stays in PHP because SQLite SUM over decimal text would use floating point.
 *
 * @phpstan-import-type CategoryActivity from CurrencyFinancialActivity
 * @phpstan-import-type AccountActivity from CurrencyFinancialActivity
 * @phpstan-import-type MonthlyActivity from CurrencyFinancialActivity
 */
class FinancialActivityCalculator
{
    public function handle(User $actor, Team $team, string $startDate, string $endDate): FinancialActivityResult
    {
        $team = Team::whereKey($team->id)->firstOrFail();
        Gate::forUser($actor)->authorize('viewAny', [AccountTransaction::class, $team]);
        Validator::make(['start_date' => $startDate, 'end_date' => $endDate], [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ])->validate();

        $transactions = AccountTransaction::forTeam($team)
            ->join('financial_accounts as activity_account', function (JoinClause $join): void {
                $join->on('activity_account.id', '=', 'account_transactions.financial_account_id')
                    ->on('activity_account.team_id', '=', 'account_transactions.team_id');
            })
            ->leftJoin('transaction_categories as activity_category', function (JoinClause $join): void {
                $join->on('activity_category.id', '=', 'account_transactions.category_id')
                    ->on('activity_category.team_id', '=', 'account_transactions.team_id');
            })
            ->leftJoin('transaction_categories as activity_parent', function (JoinClause $join): void {
                $join->on('activity_parent.id', '=', 'activity_category.parent_id')
                    ->on('activity_parent.team_id', '=', 'account_transactions.team_id');
            })
            ->where('account_transactions.status', TransactionStatus::Posted->value)
            ->whereIn('account_transactions.kind', [TransactionKind::Income->value, TransactionKind::Expense->value])
            ->where('account_transactions.excluded_from_analytics', false)
            ->where('account_transactions.posted_date', '>=', $startDate)
            /** Include SQLite date casts stored with midnight time on the final day. */
            ->where('account_transactions.posted_date', '<=', $endDate.' 23:59:59.999999')
            ->select([
                'account_transactions.amount', 'account_transactions.kind', 'account_transactions.currency_code', 'account_transactions.posted_date',
                'activity_account.id as account_id', 'activity_account.name as account_name',
                'activity_account.type as account_type', 'activity_account.archived_at as account_archived_at',
                'activity_category.id as category_id', 'activity_category.name as category_name', 'activity_category.kind as category_kind',
                'activity_category.archived_at as category_archived_at', 'activity_parent.id as parent_id', 'activity_parent.name as parent_name',
            ])->orderBy('account_transactions.id')->toBase()->cursor();

        $zero = new FinancialActivityTotals('0.00000000', '0.00000000', '0.00000000', 0);
        /** @var array<string, FinancialActivityTotals> $totals */
        $totals = [];
        /** @var array<string, array<int, CategoryActivity>> $categories */
        $categories = [];
        /** @var array<string, array<int, AccountActivity>> $accounts */
        $accounts = [];
        /** @var array<string, array<string, MonthlyActivity>> $months */
        $months = [];

        foreach ($transactions as $transaction) {
            if (! is_string($transaction->amount)) {
                throw new UnexpectedValueException('Transaction amounts must be stored and retrieved as decimal strings.');
            }
            $type = FinancialAccountType::from($transaction->account_type);
            $effect = BigDecimal::of($type->netWorthContribution($transaction->amount));
            $income = $transaction->kind === TransactionKind::Income->value ? $effect : BigDecimal::zero()->toScale(8);
            $expenses = $transaction->kind === TransactionKind::Expense->value ? $effect->negated() : BigDecimal::zero()->toScale(8);
            $code = $transaction->currency_code;
            $accountId = (int) $transaction->account_id;
            $categoryKey = $transaction->category_id === null ? 0 : (int) $transaction->category_id;
            $month = substr($transaction->posted_date, 0, 7);

            $totals[$code] = $this->add($totals[$code] ?? $zero, $income, $expenses);
            $categories[$code][$categoryKey] = [
                'categoryId' => $transaction->category_id === null ? null : $categoryKey,
                'categoryName' => $transaction->category_name ?? 'Uncategorized',
                'kind' => $transaction->category_kind,
                'parentCategoryId' => $transaction->parent_id === null ? null : (int) $transaction->parent_id,
                'parentCategoryName' => $transaction->parent_name,
                'archivedAt' => $transaction->category_archived_at === null ? null : CarbonImmutable::parse($transaction->category_archived_at)->toISOString(),
                'totals' => $this->add($categories[$code][$categoryKey]['totals'] ?? $zero, $income, $expenses),
            ];
            $accounts[$code][$accountId] = [
                'accountId' => $accountId, 'accountName' => $transaction->account_name, 'accountType' => $type,
                'archivedAt' => $transaction->account_archived_at === null ? null : CarbonImmutable::parse($transaction->account_archived_at)->toISOString(),
                'totals' => $this->add($accounts[$code][$accountId]['totals'] ?? $zero, $income, $expenses),
            ];
            $months[$code][$month] = ['month' => $month, 'totals' => $this->add($months[$code][$month]['totals'] ?? $zero, $income, $expenses)];
        }

        ksort($totals);
        $currencies = [];
        $lastMonth = CarbonImmutable::parse($endDate, 'UTC')->startOfMonth();
        foreach ($totals as $code => $currencyTotals) {
            ksort($categories[$code], SORT_NUMERIC);
            ksort($accounts[$code], SORT_NUMERIC);
            $monthly = [];
            for ($month = CarbonImmutable::parse($startDate, 'UTC')->startOfMonth(); $month <= $lastMonth; $month = $month->addMonth()) {
                $key = $month->format('Y-m');
                $monthly[] = $months[$code][$key] ?? ['month' => $key, 'totals' => $zero];
            }
            $currencies[$code] = new CurrencyFinancialActivity($code, $currencyTotals, array_values($categories[$code]), array_values($accounts[$code]), $monthly);
        }

        return new FinancialActivityResult($team->id, $startDate, $endDate, $currencies);
    }

    private function add(FinancialActivityTotals $totals, BigDecimal $income, BigDecimal $expenses): FinancialActivityTotals
    {
        $income = BigDecimal::of($totals->recordedIncome)->plus($income);
        $expenses = BigDecimal::of($totals->recordedExpenses)->plus($expenses);

        return new FinancialActivityTotals((string) $income, (string) $expenses, (string) $income->minus($expenses), $totals->transactionCount + 1);
    }
}
