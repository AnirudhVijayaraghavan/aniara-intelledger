<?php

declare(strict_types=1);

use App\Data\FxConversion;
use App\Data\FxQuote;

function fxQuoteForTypeTest(): FxQuote
{
    return new FxQuote(
        fromCurrencyCode: 'USD', toCurrencyCode: 'INR', valuationAt: '2026-10-25T12:00:00.000000Z',
        rateId: 7, baseCurrencyCode: 'USD', quoteCurrencyCode: 'INR', rate: '2.000000000000000000',
        source: 'manual', effectiveAt: '2026-10-20T00:00:00.123456Z', recordedAt: '2026-10-21T00:00:00.654321Z', isInverse: false,
    );
}

test('FX DTO serialization preserves exact amounts and the complete typed provenance contract', function () {
    $quote = fxQuoteForTypeTest();
    $amount = '9999999999999999.99999999';
    $conversion = new FxConversion($amount, $quote->apply($amount), $quote);

    expect($quote->jsonSerialize())->toBe([
        'fromCurrencyCode' => 'USD', 'toCurrencyCode' => 'INR', 'valuationAt' => '2026-10-25T12:00:00.000000Z',
        'rateId' => 7, 'baseCurrencyCode' => 'USD', 'quoteCurrencyCode' => 'INR', 'rate' => '2.000000000000000000',
        'source' => 'manual', 'effectiveAt' => '2026-10-20T00:00:00.123456Z', 'recordedAt' => '2026-10-21T00:00:00.654321Z', 'isInverse' => false,
    ]);
    expect($conversion->jsonSerialize())->toBe([
        'amount' => '9999999999999999.99999999',
        'convertedAmount' => '19999999999999999.99999998',
        'quote' => $quote,
    ]);
    expect(json_decode(json_encode($conversion, JSON_THROW_ON_ERROR), true))->toBe([
        'amount' => '9999999999999999.99999999',
        'convertedAmount' => '19999999999999999.99999998',
        'quote' => $quote->jsonSerialize(),
    ]);
});

test('FX quote input validation rejects non-string amounts without coercion', function (mixed $amount) {
    expect(fn () => fxQuoteForTypeTest()->apply($amount))->toThrow(InvalidArgumentException::class, 'Conversion amounts must be plain decimal strings.');
})->with(['integer' => [1], 'float' => [1.25], 'boolean' => [true], 'null' => [null]]);

test('strict FX conversion callers reject scalar money coercion', function (mixed $amount) {
    expect(fn () => new FxConversion($amount, '1.00000000', fxQuoteForTypeTest()))->toThrow(TypeError::class);
})->with(['integer' => [1], 'float' => [1.25], 'boolean' => [true]]);

test('strict FX quote callers reject scalar rate coercion', function (mixed $rate) {
    expect(fn () => new FxQuote('USD', 'INR', '2026-10-25T12:00:00.000000Z', null, 'USD', 'INR', $rate, 'manual', null, null, false))->toThrow(TypeError::class);
})->with(['integer' => [2], 'float' => [2.5]]);
