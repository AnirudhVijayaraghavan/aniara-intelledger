<?php

declare(strict_types=1);

use App\Enums\FinancialAccountType;
use App\Models\AccountBalance;
use Brick\Math\Exception\RoundingNecessaryException;

test('account types produce exact signed decimal contributions', function (FinancialAccountType $type, string $amount, string $expected) {
    expect($type->netWorthContribution($amount))->toBe($expected);
})->with([
    'checking' => [FinancialAccountType::Checking, '1000.25', '1000.25000000'],
    'savings' => [FinancialAccountType::Savings, '0.1', '0.10000000'],
    'brokerage' => [FinancialAccountType::Brokerage, '9999999999999999.99999999', '9999999999999999.99999999'],
    'other asset' => [FinancialAccountType::OtherAsset, '10', '10.00000000'],
    'credit card owed' => [FinancialAccountType::CreditCard, '200.25', '-200.25000000'],
    'loan owed' => [FinancialAccountType::Loan, '9999999999999999.99999999', '-9999999999999999.99999999'],
    'other liability' => [FinancialAccountType::OtherLiability, '10', '-10.00000000'],
    'overdraft' => [FinancialAccountType::Checking, '-50.25', '-50.25000000'],
    'credit card overpayment' => [FinancialAccountType::CreditCard, '-50.25', '50.25000000'],
]);

test('balance values reject floating point inputs', function () {
    expect(fn () => new AccountBalance(['amount' => 0.1]))->toThrow(InvalidArgumentException::class, 'Balance amounts must be decimal strings.');
});

test('balance values reject silent rounding', function () {
    expect(fn () => new AccountBalance(['amount' => '0.123456789']))->toThrow(RoundingNecessaryException::class);
});

test('balance values reject overflow beyond the database precision', function () {
    expect(fn () => new AccountBalance(['amount' => '10000000000000000']))->toThrow(InvalidArgumentException::class, 'Balance amount exceeds DECIMAL(24,8).');
});

test('balance values retain exact maximum precision before persistence', function () {
    $balance = new AccountBalance(['amount' => '9999999999999999.99999999']);

    expect($balance->amount)->toBe('9999999999999999.99999999');
});

test('strict financial callers reject scalar coercion before account contribution arithmetic', function (mixed $amount) {
    expect(fn () => FinancialAccountType::Checking->netWorthContribution($amount))->toThrow(TypeError::class);
})->with(['integer' => 100, 'float' => 0.1, 'boolean' => true]);
