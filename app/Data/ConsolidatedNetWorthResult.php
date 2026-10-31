<?php

declare(strict_types=1);

namespace App\Data;

use JsonSerializable;

/** Definitive consolidated totals are null whenever any balances or rates are missing. */
readonly class ConsolidatedNetWorthResult implements JsonSerializable
{
    public bool $isComplete;

    /**
     * @param  array<string, FxQuote>  $fxQuotes
     * @param  list<string>  $missingRateCurrencies
     */
    public function __construct(
        public NetWorthResult $original,
        public string $reportingCurrencyCode,
        public string $valuationAt,
        public ?string $totalAssets,
        public ?string $totalLiabilities,
        public ?string $netWorth,
        public array $fxQuotes,
        public array $missingRateCurrencies,
    ) {
        $this->isComplete = $original->isComplete && $missingRateCurrencies === [];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [...get_object_vars($this), 'fxQuotes' => (object) $this->fxQuotes];
    }
}
