<?php

namespace App\Data;

use JsonSerializable;

readonly class FinancialActivityTotals implements JsonSerializable
{
    public function __construct(
        public string $recordedIncome,
        public string $recordedExpenses,
        public string $netRecordedIncome,
        public int $transactionCount,
    ) {}

    /** @return array<string, string|int> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
