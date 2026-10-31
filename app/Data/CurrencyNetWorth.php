<?php

declare(strict_types=1);

namespace App\Data;

use JsonSerializable;

/** Totals include known observations only; incomplete results identify missing accounts. */
readonly class CurrencyNetWorth implements JsonSerializable
{
    public bool $isComplete;

    /**
     * @param  array<string, string>  $assetsByAccountType
     * @param  array<string, string>  $liabilitiesByAccountType
     * @param  list<AccountNetWorthContribution>  $accounts
     * @param  list<int>  $missingAccountIds
     */
    public function __construct(
        public string $currencyCode,
        public string $totalAssets,
        public string $totalLiabilities,
        public string $netWorth,
        public array $assetsByAccountType,
        public array $liabilitiesByAccountType,
        public array $accounts,
        public array $missingAccountIds,
    ) {
        $this->isComplete = $missingAccountIds === [];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            ...get_object_vars($this),
            'assetsByAccountType' => (object) $this->assetsByAccountType,
            'liabilitiesByAccountType' => (object) $this->liabilitiesByAccountType,
        ];
    }
}
