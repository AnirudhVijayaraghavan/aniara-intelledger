<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\ConsolidatedNetWorthResult;
use App\Exceptions\MissingExchangeRate;
use App\Models\Team;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConsolidatedNetWorthCalculator
{
    public function __construct(
        private NetWorthCalculator $netWorth,
        private ExchangeRateService $exchangeRates,
    ) {}

    /** Convert each currency's asset/liability subtotal once, then sum and subtract. */
    public function handle(User $actor, Team $team, string $asOfDate): ConsolidatedNetWorthResult
    {
        return DB::transaction(function () use ($actor, $team, $asOfDate): ConsolidatedNetWorthResult {
            $team = Team::whereKey($team->id)->firstOrFail();
            $original = $this->netWorth->handle($actor, $team, $asOfDate);
            $valuationAt = CarbonImmutable::parse($original->asOfDate, 'UTC')->endOfDay();
            $valuationTimestamp = $valuationAt->toISOString();
            if ($valuationTimestamp === null) {
                throw new InvalidArgumentException('The net-worth valuation timestamp must be valid.');
            }
            $assets = BigDecimal::zero()->toScale(8);
            $liabilities = BigDecimal::zero()->toScale(8);
            $quotes = [];
            $missingRates = [];

            foreach ($original->currencies as $code => $currency) {
                try {
                    $quote = $this->exchangeRates->quote($actor, $team, $code, $team->reporting_currency_code, $valuationAt);
                } catch (MissingExchangeRate) {
                    $missingRates[] = $code;

                    continue;
                }
                $quotes[$code] = $quote;
                $assets = $assets->plus($quote->apply($currency->totalAssets));
                $liabilities = $liabilities->plus($quote->apply($currency->totalLiabilities));
            }

            $complete = $original->isComplete && $missingRates === [];

            return new ConsolidatedNetWorthResult(
                original: $original,
                reportingCurrencyCode: $team->reporting_currency_code,
                valuationAt: $valuationTimestamp,
                totalAssets: $complete ? (string) $assets : null,
                totalLiabilities: $complete ? (string) $liabilities : null,
                netWorth: $complete ? (string) $assets->minus($liabilities) : null,
                fxQuotes: $quotes,
                missingRateCurrencies: $missingRates,
            );
        });
    }
}
