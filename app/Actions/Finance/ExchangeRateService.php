<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\FxConversion;
use App\Data\FxQuote;
use App\Exceptions\MissingExchangeRate;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use UnexpectedValueException;

class ExchangeRateService
{
    public function convert(User $actor, Team $team, mixed $amount, string $from, string $to, CarbonImmutable $valuationAt): FxConversion
    {
        if (! is_string($amount)) {
            throw new InvalidArgumentException('Conversion amounts must be decimal strings.');
        }

        $quote = $this->quote($actor, $team, $from, $to, $valuationAt);
        $converted = $quote->apply($amount);

        return new FxConversion($amount, $converted, $quote);
    }

    /**
     * Select the freshest eligible observation in either orientation.
     * Ties use recording time, then ID, descending. UTC is used throughout.
     */
    public function quote(User $actor, Team $team, string $from, string $to, CarbonImmutable $valuationAt): FxQuote
    {
        $team = Team::whereKey($team->id)->firstOrFail();
        Gate::forUser($actor)->authorize('viewAny', [ExchangeRate::class, $team]);
        $from = strtoupper($from);
        $to = strtoupper($to);
        $currencies = array_unique([$from, $to]);
        if (Currency::whereIn('code', $currencies)->count() !== count($currencies)) {
            throw ValidationException::withMessages(['currency' => 'Both currencies must exist.']);
        }
        $valuationAt = $valuationAt->utc();
        $valuationTimestamp = $valuationAt->toISOString();
        if ($valuationTimestamp === null) {
            throw new InvalidArgumentException('The FX valuation timestamp must be valid.');
        }

        if ($from === $to) {
            return new FxQuote($from, $to, $valuationTimestamp, null, $from, $to, '1', 'identity', null, null, false);
        }

        $rate = ExchangeRate::forTeam($team)
            ->where(fn ($query) => $query
                ->where(fn ($direct) => $direct->where('base_currency_code', $from)->where('quote_currency_code', $to))
                ->orWhere(fn ($inverse) => $inverse->where('base_currency_code', $to)->where('quote_currency_code', $from)))
            ->where('effective_at', '<=', $valuationAt->format('Y-m-d H:i:s.u'))
            ->orderByDesc('effective_at')->orderByDesc('recorded_at')->orderByDesc('id')->first();

        if ($rate === null) {
            throw new MissingExchangeRate("No eligible {$from}/{$to} rate at {$valuationTimestamp}.");
        }

        $effectiveAt = $rate->effective_at?->toISOString();
        $recordedAt = $rate->recorded_at?->toISOString();
        if ($effectiveAt === null || $recordedAt === null) {
            throw new UnexpectedValueException('Stored exchange rate timestamps must be valid.');
        }

        return new FxQuote(
            fromCurrencyCode: $from,
            toCurrencyCode: $to,
            valuationAt: $valuationTimestamp,
            rateId: $rate->id,
            baseCurrencyCode: $rate->base_currency_code,
            quoteCurrencyCode: $rate->quote_currency_code,
            rate: ExchangeRate::normalizeRate($rate->rate),
            source: $rate->source,
            effectiveAt: $effectiveAt,
            recordedAt: $recordedAt,
            isInverse: strtoupper($rate->base_currency_code) !== $from,
        );
    }
}
