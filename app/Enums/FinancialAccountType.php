<?php

declare(strict_types=1);

namespace App\Enums;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

enum FinancialAccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Brokerage = 'brokerage';
    case CreditCard = 'credit_card';
    case Loan = 'loan';
    case OtherAsset = 'other_asset';
    case OtherLiability = 'other_liability';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Checking',
            self::Savings => 'Savings',
            self::Brokerage => 'Brokerage',
            self::CreditCard => 'Credit card',
            self::Loan => 'Loan',
            self::OtherAsset => 'Other asset',
            self::OtherLiability => 'Other liability',
        };
    }

    public function isLiability(): bool
    {
        return in_array($this, [self::CreditCard, self::Loan, self::OtherLiability], true);
    }

    public function netWorthContribution(string $amount): string
    {
        $value = BigDecimal::of($amount)->toScale(8, RoundingMode::Unnecessary);

        return (string) ($this->isLiability() ? $value->negated() : $value);
    }
}
