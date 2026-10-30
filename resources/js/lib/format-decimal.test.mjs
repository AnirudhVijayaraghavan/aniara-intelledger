import assert from 'node:assert/strict';
import test from 'node:test';
import { formatDecimal } from './format-decimal.ts';

for (const [amount, currency, expected] of [
    ['9999999999999999.99999999', 'USD', '9,999,999,999,999,999.99999999'],
    ['19999999999999999.99999998', 'USD', '19,999,999,999,999,999.99999998'],
    ['1234567890123456.12345678', 'INR', '1,23,45,67,89,01,23,456.12345678'],
    ['-50.25000000', 'USD', '-50.25'],
    ['0.00000001', 'INR', '0.00000001'],
    ['0.00000000', 'USD', '0.00'],
    ['1234.10000000', 'INR', '1,234.10'],
    ['100000.00000000', 'INR', '1,00,000.00'],
    ['83.123456789012345678', 'INR', '83.123456789012345678'],
    ['0.000000000000000001', 'USD', '0.000000000000000001'],
]) {
    void test(`formats ${currency} ${amount} without precision loss`, () => {
        assert.equal(formatDecimal(amount, currency), expected);
    });
}

for (const value of ['', 'NaN', '1e3', '1,000.00']) {
    void test(`rejects invalid decimal text ${JSON.stringify(value)}`, () => {
        assert.throws(() => formatDecimal(value, 'USD'), TypeError);
    });
}
