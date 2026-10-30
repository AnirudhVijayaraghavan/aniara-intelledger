<?php

declare(strict_types=1);

namespace App\Data;

use JsonSerializable;

readonly class NetWorthResult implements JsonSerializable
{
    public bool $isComplete;

    /** @param array<string, CurrencyNetWorth> $currencies */
    public function __construct(
        public int $teamId,
        public string $asOfDate,
        public array $currencies,
    ) {
        $this->isComplete = array_all($currencies, fn (CurrencyNetWorth $currency): bool => $currency->isComplete);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [...get_object_vars($this), 'currencies' => (object) $this->currencies];
    }
}
