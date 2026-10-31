import assert from 'node:assert/strict';
import test from 'node:test';
import { activityChartScale, hasRecordedAmount } from './activity-chart.ts';

void test('chart projections retain negative adjustments and use one magnitude scale', () => {
    assert.deepEqual(
        activityChartScale(['-25.12345678', '100.00000000', '0.00000000']),
        {
            values: [-25.12345678, 100, 0],
            maximum: 100,
            hasNegative: true,
        },
    );
    assert.equal(activityChartScale(['-0.00000001'])?.hasNegative, true);
});

void test('zero months have a zero display scale without claiming missing values are zero', () => {
    assert.deepEqual(activityChartScale(['0.00000000', '0.00000000']), {
        values: [0, 0],
        maximum: 0,
        hasNegative: false,
    });
    assert.equal(activityChartScale(['']), null);
});

void test('invalid and non-finite chart inputs decline projection', () => {
    for (const invalid of [
        'NaN',
        'Infinity',
        '1e3',
        ' 1.00 ',
        '9'.repeat(400),
    ]) {
        assert.equal(activityChartScale([invalid]), null);
    }
});

void test('presentation retains negative and positive recorded values while recognizing exact zero', () => {
    for (const amount of ['0', '0.00000000', '-0.00000000']) {
        assert.equal(hasRecordedAmount(amount), false);
    }
    for (const amount of [
        '-0.00000001',
        '0.00000001',
        '9999999999999999.12345678',
    ]) {
        assert.equal(hasRecordedAmount(amount), true);
    }
});
