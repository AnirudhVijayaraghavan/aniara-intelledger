import assert from 'node:assert/strict';
import test from 'node:test';
import {
    formatUtcTimestamp,
    isPriorDate,
    utcInputToIso,
} from './financial-dates.ts';

void test('UTC form times preserve the entered day and time without local timezone conversion', () => {
    assert.equal(utcInputToIso('2026-10-25T12:30'), '2026-10-25T12:30:00Z');
    assert.equal(utcInputToIso('2026-10-25T12:30:45'), '2026-10-25T12:30:45Z');
    assert.equal(utcInputToIso(''), '');
});

void test('prior-date flags compare UTC economic days rather than recording times', () => {
    assert.equal(
        isPriorDate(
            '2026-10-24T23:59:59.999999Z',
            '2026-10-25T23:59:59.999999Z',
        ),
        true,
    );
    assert.equal(
        isPriorDate(
            '2026-10-25T00:00:00.000000Z',
            '2026-10-25T23:59:59.999999Z',
        ),
        false,
    );
    assert.equal(
        isPriorDate(
            '2026-10-26T00:00:00.000000Z',
            '2026-10-25T23:59:59.999999Z',
        ),
        false,
    );
});

void test('UTC labels preserve the full recorded timestamp precision', () => {
    assert.equal(
        formatUtcTimestamp('2026-10-25T12:30:45.123456Z'),
        '2026-10-25 12:30:45.123456 UTC',
    );
});
