<?php

namespace App\Data;

use JsonSerializable;

readonly class FinancialActivityResult implements JsonSerializable
{
    public string $label;

    public string $dateBasis;

    public string $coverageNote;

    /** @param array<string, CurrencyFinancialActivity> $currencies */
    public function __construct(
        public int $teamId,
        public string $startDate,
        public string $endDate,
        public array $currencies,
    ) {
        $this->label = 'Recorded income and spending';
        $this->dateBasis = 'posted_date';
        $this->coverageNote = 'Includes recorded qualifying transactions only. Household transaction coverage may be incomplete; credit purchases are spending, not necessarily direct cash movements.';
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [...get_object_vars($this), 'currencies' => (object) $this->currencies];
    }
}
