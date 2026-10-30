<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\AccountNetWorthContribution;
use App\Data\CurrencyNetWorth;
use App\Data\NetWorthResult;
use App\Models\AccountBalance;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use UnexpectedValueException;

class NetWorthCalculator
{
    public function handle(User $actor, Team $team, string $asOfDate): NetWorthResult
    {
        $team = Team::whereKey($team->id)->firstOrFail();
        Gate::forUser($actor)->authorize('viewAny', [FinancialAccount::class, $team]);
        Validator::make(['as_of_date' => $asOfDate], ['as_of_date' => ['required', 'date_format:Y-m-d']])->validate();

        /** The day boundary also includes SQLite date casts stored with midnight time. */
        $latestBalance = AccountBalance::forTeam($team)->notVoided()
            ->select('account_balances.id')
            ->whereColumn('account_balances.financial_account_id', 'financial_accounts.id')
            ->whereColumn('account_balances.team_id', 'financial_accounts.team_id')
            ->where('as_of_date', '<=', $asOfDate.' 23:59:59.999999')
            ->newestFirst()->limit(1);

        $accounts = FinancialAccount::forTeam($team)
            ->leftJoin('financial_institutions as valuation_institution', function (JoinClause $join): void {
                $join->on('valuation_institution.id', '=', 'financial_accounts.financial_institution_id')
                    ->on('valuation_institution.team_id', '=', 'financial_accounts.team_id');
            })
            ->leftJoin('account_balances as valuation_balance', function (JoinClause $join) use ($latestBalance): void {
                $join->on('valuation_balance.financial_account_id', '=', 'financial_accounts.id')
                    ->on('valuation_balance.team_id', '=', 'financial_accounts.team_id')
                    ->where('valuation_balance.id', '=', $latestBalance);
            })
            ->select([
                'financial_accounts.id', 'financial_accounts.name', 'financial_accounts.type',
                'financial_accounts.currency_code', 'financial_accounts.archived_at',
                'valuation_institution.id as valuation_institution_id',
                'valuation_institution.name as valuation_institution_name',
                'valuation_balance.id as valuation_balance_id',
                'valuation_balance.amount as valuation_amount',
                'valuation_balance.as_of_date as valuation_as_of_date',
                'valuation_balance.recorded_at as valuation_recorded_at',
            ])
            ->orderBy('financial_accounts.currency_code')->orderBy('financial_accounts.id')
            ->get()->map(fn (FinancialAccount $account): AccountNetWorthContribution => $this->contribution($account));

        $currencies = [];

        foreach ($accounts->groupBy('currencyCode') as $currencyCode => $contributions) {
            $assets = BigDecimal::zero()->toScale(8);
            $liabilities = BigDecimal::zero()->toScale(8);
            $assetsByType = [];
            $liabilitiesByType = [];
            $missingIds = [];

            foreach ($contributions as $contribution) {
                if ($contribution->balanceAmount === null) {
                    $missingIds[] = $contribution->accountId;

                    continue;
                }

                $type = $contribution->type->value;
                $amount = BigDecimal::of($contribution->balanceAmount);

                if ($contribution->type->isLiability()) {
                    $liabilities = $liabilities->plus($amount);
                    $liabilitiesByType[$type] = (string) BigDecimal::of($liabilitiesByType[$type] ?? '0')->plus($amount)->toScale(8);
                } else {
                    $assets = $assets->plus($amount);
                    $assetsByType[$type] = (string) BigDecimal::of($assetsByType[$type] ?? '0')->plus($amount)->toScale(8);
                }
            }

            ksort($assetsByType);
            ksort($liabilitiesByType);
            $currencies[$currencyCode] = new CurrencyNetWorth(
                currencyCode: (string) $currencyCode,
                totalAssets: (string) $assets,
                totalLiabilities: (string) $liabilities,
                netWorth: (string) $assets->minus($liabilities),
                assetsByAccountType: $assetsByType,
                liabilitiesByAccountType: $liabilitiesByType,
                accounts: array_values($contributions->all()),
                missingAccountIds: $missingIds,
            );
        }

        return new NetWorthResult($team->id, $asOfDate, $currencies);
    }

    private function contribution(FinancialAccount $account): AccountNetWorthContribution
    {
        $balanceId = $account->getRawOriginal('valuation_balance_id');
        $amount = $account->getRawOriginal('valuation_amount');

        if ($balanceId !== null && ! is_string($amount)) {
            throw new UnexpectedValueException('Balance amounts must be stored and retrieved as decimal strings.');
        }

        $amount = $balanceId === null ? null : (string) BigDecimal::of($amount)->toScale(8, RoundingMode::Unnecessary);

        return new AccountNetWorthContribution(
            accountId: $account->id,
            accountName: $account->name,
            type: $account->type,
            currencyCode: $account->currency_code,
            archivedAt: $account->archived_at?->toISOString(),
            balanceObservationId: $balanceId === null ? null : (int) $balanceId,
            balanceAsOfDate: $balanceId === null ? null : CarbonImmutable::parse((string) $account->getRawOriginal('valuation_as_of_date'))->toDateString(),
            balanceRecordedAt: $balanceId === null ? null : CarbonImmutable::parse((string) $account->getRawOriginal('valuation_recorded_at'))->toISOString(),
            balanceAmount: $amount,
            netWorthContribution: $amount === null ? null : $account->type->netWorthContribution($amount),
            institutionId: $account->getRawOriginal('valuation_institution_id') === null ? null : (int) $account->getRawOriginal('valuation_institution_id'),
            institutionName: $account->getRawOriginal('valuation_institution_name') === null ? null : (string) $account->getRawOriginal('valuation_institution_name'),
        );
    }
}
