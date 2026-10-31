<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\FinancialAccountType;
use JsonSerializable;

readonly class AccountNetWorthContribution implements JsonSerializable
{
    public bool $missingData;

    public string $typeLabel;

    public bool $isLiability;

    public function __construct(
        public int $accountId,
        public string $accountName,
        public FinancialAccountType $type,
        public string $currencyCode,
        public ?string $archivedAt,
        public ?int $balanceObservationId,
        public ?string $balanceAsOfDate,
        public ?string $balanceRecordedAt,
        public ?string $balanceAmount,
        public ?string $netWorthContribution,
        public ?int $institutionId = null,
        public ?string $institutionName = null,
    ) {
        $this->missingData = $balanceObservationId === null;
        $this->typeLabel = $type->label();
        $this->isLiability = $type->isLiability();
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
