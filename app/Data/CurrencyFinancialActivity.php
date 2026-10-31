<?php

namespace App\Data;

use App\Enums\FinancialAccountType;
use JsonSerializable;

/**
 * Direct-category buckets do not roll child activity into parent buckets.
 *
 * @phpstan-type CategoryActivity array{categoryId: int|null, categoryName: string, kind: string|null, parentCategoryId: int|null, parentCategoryName: string|null, archivedAt: string|null, totals: FinancialActivityTotals}
 * @phpstan-type AccountActivity array{accountId: int, accountName: string, accountType: FinancialAccountType, archivedAt: string|null, totals: FinancialActivityTotals}
 * @phpstan-type MonthlyActivity array{month: string, totals: FinancialActivityTotals}
 */
readonly class CurrencyFinancialActivity implements JsonSerializable
{
    /**
     * @param  list<CategoryActivity>  $byCategory
     * @param  list<AccountActivity>  $byAccount
     * @param  list<MonthlyActivity>  $monthly
     */
    public function __construct(
        public string $currencyCode,
        public FinancialActivityTotals $totals,
        public array $byCategory,
        public array $byAccount,
        public array $monthly,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
