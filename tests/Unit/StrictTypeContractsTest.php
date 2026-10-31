<?php

declare(strict_types=1);

use App\Actions\Finance\ConsolidatedNetWorthCalculator;
use App\Actions\Finance\ExchangeRateService;
use App\Actions\Finance\NetWorthCalculator;
use App\Data\AccountNetWorthContribution;
use App\Data\ConsolidatedNetWorthResult;
use App\Data\CurrencyNetWorth;
use App\Data\FxConversion;
use App\Data\FxQuote;
use App\Data\NetWorthResult;
use App\Enums\AccountBalanceSource;
use App\Enums\FinancialAccountType;

arch('financial valuation contracts and their calculation callers use strict types')
    ->expect([
        AccountNetWorthContribution::class,
        CurrencyNetWorth::class,
        NetWorthResult::class,
        ConsolidatedNetWorthResult::class,
        FxQuote::class,
        FxConversion::class,
        FinancialAccountType::class,
        AccountBalanceSource::class,
        NetWorthCalculator::class,
        ConsolidatedNetWorthCalculator::class,
        ExchangeRateService::class,
    ])
    ->toUseStrictTypes();

test('strict valuation callers reject numeric or boolean amounts instead of coercing them into DTO strings', function (mixed $amount) {
    expect(fn () => new CurrencyNetWorth('USD', $amount, '0.00000000', '0.00000000', [], [], [], []))
        ->toThrow(TypeError::class);
})->with(['integer' => 100, 'float' => 0.1, 'boolean' => true]);

test('strict net worth DTOs preserve native enum identifiers nullable metadata and exact JSON decimal strings', function () {
    $account = new AccountNetWorthContribution(
        accountId: 7, accountName: 'Recorded card', type: FinancialAccountType::CreditCard, currencyCode: 'INR',
        archivedAt: null, balanceObservationId: 11, balanceAsOfDate: '2026-10-20',
        balanceRecordedAt: '2026-10-21T12:00:00.123456Z', balanceAmount: '-9999999999999999.12345678',
        netWorthContribution: '9999999999999999.12345678',
    );
    $currency = new CurrencyNetWorth(
        'INR', '0.00000000', '-9999999999999999.12345678', '9999999999999999.12345678',
        [], ['credit_card' => '-9999999999999999.12345678'], [$account], [],
    );
    $original = new NetWorthResult(3, '2026-10-20', ['INR' => $currency]);
    $consolidated = new ConsolidatedNetWorthResult(
        $original, 'INR', '2026-10-20T23:59:59.999999Z', '0.00000000', '-9999999999999999.12345678',
        '9999999999999999.12345678', [], [],
    );

    $json = json_encode($consolidated, JSON_THROW_ON_ERROR);
    $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    expect($payload['isComplete'])->toBeTrue();
    expect($payload['totalAssets'])->toBe('0.00000000');
    expect($payload['totalLiabilities'])->toBe('-9999999999999999.12345678');
    expect($payload['netWorth'])->toBe('9999999999999999.12345678');
    expect($payload['original']['teamId'])->toBe(3);
    expect($payload['original']['currencies']['INR']['accounts'][0])->toMatchArray([
        'accountId' => 7, 'type' => 'credit_card', 'isLiability' => true, 'missingData' => false,
        'balanceAmount' => '-9999999999999999.12345678', 'netWorthContribution' => '9999999999999999.12345678',
        'balanceRecordedAt' => '2026-10-21T12:00:00.123456Z', 'institutionId' => null, 'archivedAt' => null,
    ]);
    expect($json)->toContain('"assetsByAccountType":{}', '"fxQuotes":{}');
    expect(json_encode(['source' => AccountBalanceSource::Manual, 'type' => FinancialAccountType::CreditCard], JSON_THROW_ON_ERROR))
        ->toBe('{"source":"manual","type":"credit_card"}');
});
