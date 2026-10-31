<?php

declare(strict_types=1);

use App\Actions\Finance\ExchangeRateService;
use App\Actions\Finance\RecordExchangeRate;
use App\Enums\TeamRole;
use App\Exceptions\MissingExchangeRate;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use Brick\Math\Exception\RoundingNecessaryException;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

test('FX rejects an invalid valuation timestamp before producing a typed quote', function () {
    $actor = User::factory()->create();

    expect(fn () => app(ExchangeRateService::class)->convert($actor, $actor->personalTeam(), '1', 'USD', 'USD', CarbonImmutable::parse('0000-01-01', 'UTC')))
        ->toThrow(InvalidArgumentException::class, 'The FX valuation timestamp must be valid.');
    $this->assertDatabaseCount('exchange_rates', 0);
});

test('FX rejects invalid stored timestamp provenance instead of emitting missing dates', function (string $field) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    ExchangeRate::factory()->create(array_replace([
        'team_id' => $team->id, 'effective_at' => '2026-10-20', 'recorded_at' => '2026-10-21',
    ], [$field => '0000-01-01']));

    expect(fn () => app(ExchangeRateService::class)->quote($actor, $team, 'USD', 'INR', CarbonImmutable::parse('2026-10-25', 'UTC')))
        ->toThrow(UnexpectedValueException::class, 'Stored exchange rate timestamps must be valid.');
    $this->assertDatabaseCount('exchange_rates', 1);
})->with(['effective_at', 'recorded_at']);

test('FX supports direct and inverse conversion in both currency directions with provenance', function (string $base, string $quote, string $rate, string $from, string $to, string $amount, string $expected, bool $inverse) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $observation = ExchangeRate::factory()->create([
        'team_id' => $team->id, 'base_currency_code' => $base, 'quote_currency_code' => $quote,
        'rate' => $rate, 'effective_at' => '2026-10-20T12:00:00.123456Z', 'recorded_at' => '2026-10-21T10:00:00.654321Z',
    ]);

    $result = app(ExchangeRateService::class)->convert($actor, $team, $amount, $from, $to, CarbonImmutable::parse('2026-10-25T12:00:00Z'));

    expect($result->convertedAmount)->toBe($expected);
    expect($result->amount)->toBe($amount);
    expect($result->quote->rateId)->toBe($observation->id);
    expect($result->quote->isInverse)->toBe($inverse);
    expect($result->quote->rate)->toBe($observation->rate);
    expect($result->quote->source)->toBe('manual');
    expect($result->quote->effectiveAt)->toBe('2026-10-20T12:00:00.123456Z');
    expect($result->quote->recordedAt)->toBe('2026-10-21T10:00:00.654321Z');
})->with([
    ['USD', 'INR', '80', 'USD', 'INR', '100', '8000.00000000', false],
    ['USD', 'INR', '80', 'INR', 'USD', '8000', '100.00000000', true],
    ['INR', 'USD', '0.0125', 'INR', 'USD', '8000', '100.00000000', false],
    ['INR', 'USD', '0.0125', 'USD', 'INR', '100', '8000.00000000', true],
    ['USD', 'INR', '80', 'INR', 'USD', '-8000', '-100.00000000', true],
]);

test('same currency conversion preserves the original decimal string and needs no stored rate', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();

    $result = app(ExchangeRateService::class)->convert($actor, $team, '-123.450000000', 'USD', 'USD', CarbonImmutable::parse('2020-01-01T00:00:00Z'));

    expect($result->convertedAmount)->toBe('-123.450000000');
    expect($result->quote->source)->toBe('identity');
    expect($result->quote->rateId)->toBeNull();
    expect($result->quote->effectiveAt)->toBeNull();
    $this->assertDatabaseCount('exchange_rates', 0);
});

test('historical selection uses effective time then recording time across both pair orientations', function (string $at, string $expected, bool $inverse) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    foreach ([
        ['USD', 'INR', '80', '2026-10-20T12:00:00Z', '2026-10-21T00:00:00Z'],
        ['USD', 'INR', '81', '2026-10-21T00:00:00Z', '2026-10-22T00:00:00Z'],
        ['USD', 'INR', '82', '2026-10-21T00:00:00Z', '2026-10-23T00:00:00Z'],
        ['INR', 'USD', '0.01', '2026-10-22T00:00:00Z', '2026-10-23T00:00:00Z'],
        ['USD', 'INR', '99', '2026-10-26T00:00:00Z', '2026-10-26T00:00:00Z'],
    ] as [$base, $quote, $rate, $effective, $recorded]) {
        ExchangeRate::factory()->create(['team_id' => $team->id, 'base_currency_code' => $base, 'quote_currency_code' => $quote, 'rate' => $rate, 'effective_at' => $effective, 'recorded_at' => $recorded]);
    }

    $result = app(ExchangeRateService::class)->convert($actor, $team, '1', 'USD', 'INR', CarbonImmutable::parse($at));

    expect($result->convertedAmount)->toBe($expected);
    expect($result->quote->isInverse)->toBe($inverse);
})->with([
    ['2026-10-20T12:00:00Z', '80.00000000', false],
    ['2026-10-21T12:00:00Z', '82.00000000', false],
    ['2026-10-22T00:00:00Z', '100.00000000', true],
]);

test('rate selection breaks equal effective and recording timestamps by ID', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $attributes = ['team_id' => $team->id, 'effective_at' => '2026-10-20T00:00:00Z', 'recorded_at' => '2026-10-21T00:00:00Z'];
    ExchangeRate::factory()->create($attributes + ['rate' => '80']);
    $newer = ExchangeRate::factory()->create($attributes + ['base_currency_code' => 'INR', 'quote_currency_code' => 'USD', 'rate' => '0.01']);

    $result = app(ExchangeRateService::class)->convert($actor, $team, '1', 'USD', 'INR', CarbonImmutable::parse('2026-10-25T00:00:00Z'));

    expect($result->convertedAmount)->toBe('100.00000000');
    expect($result->quote->rateId)->toBe($newer->id);
});

test('rates are exact decimal strings through persistence and conversion rounds once with half even', function (string $rate, string $amount, string $expected, bool $inverse) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $row = ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => $rate, 'effective_at' => '2026-10-20T00:00:00Z']);
    $result = app(ExchangeRateService::class)->convert($actor, $team, $amount, $inverse ? 'INR' : 'USD', $inverse ? 'USD' : 'INR', CarbonImmutable::parse('2026-10-25T00:00:00Z'));

    expect($row->fresh()->rate)->toBe(ExchangeRate::normalizeRate($rate));
    expect($result->convertedAmount)->toBe($expected);
    expect(json_decode(json_encode($result, JSON_THROW_ON_ERROR), true)['convertedAmount'])->toBe($expected);
})->with([
    ['83.123456789012345678', '1', '83.12345679', false],
    ['2', '9999999999999999.99999999', '19999999999999999.99999998', false],
    ['1.000000000000000001', '9999999999999999.99999999', '10000000000000000.00999999', false],
    ['3', '1', '0.33333333', true],
    ['3', '2', '0.66666667', true],
    ['0.000000005', '1', '0.00000000', false],
    ['0.000000015', '1', '0.00000002', false],
    ['0.000000015', '-1', '-0.00000002', false],
]);

test('FX storage rejects invalid rates rather than rounding or accepting floats', function (mixed $rate, string $exception) {
    expect(fn () => new ExchangeRate(['rate' => $rate]))->toThrow($exception);
})->with([
    ['0', InvalidArgumentException::class], ['-1', InvalidArgumentException::class],
    [83.25, InvalidArgumentException::class], ['1e-2', InvalidArgumentException::class],
    ['NaN', InvalidArgumentException::class], ['1000000000000000000', InvalidArgumentException::class],
    ['0.1234567890123456789', RoundingNecessaryException::class],
]);

test('database constraints reject nonpositive rates and duplicate observations', function (string $case) {
    $row = ExchangeRate::factory()->create();
    $attributes = $row->getAttributes();
    unset($attributes['id']);
    if ($case !== 'duplicate') {
        $attributes['rate'] = $case === 'zero' ? '0' : '-1';
        $attributes['recorded_at'] = '2030-01-01 00:00:00.000000';
    }

    expect(fn () => DB::table('exchange_rates')->insert($attributes))->toThrow(QueryException::class);
})->with(['zero', 'negative', 'duplicate']);

test('missing historical rates fail explicitly instead of using a future rate', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    ExchangeRate::factory()->create(['team_id' => $team->id, 'effective_at' => '2026-10-25T00:00:00Z']);

    expect(fn () => app(ExchangeRateService::class)->convert($actor, $team, '1', 'USD', 'INR', CarbonImmutable::parse('2026-10-24T23:59:59Z')))->toThrow(MissingExchangeRate::class);
});

test('FX normalizes offset timestamps to UTC before comparing effective time', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $rate = ExchangeRate::factory()->create(['team_id' => $team->id, 'effective_at' => '2026-10-25T12:00:00.123456+05:30']);

    $result = app(ExchangeRateService::class)->convert($actor, $team, '1', 'USD', 'INR', CarbonImmutable::parse('2026-10-25T06:30:00.123456Z'));

    expect($result->quote->effectiveAt)->toBe('2026-10-25T06:30:00.123456Z');
    expect($result->quote->rateId)->toBe($rate->id);
});

test('conversion supports other registered currencies without currency specific arithmetic', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    Currency::factory()->create(['code' => 'EUR']);
    ExchangeRate::factory()->create(['team_id' => $team->id, 'base_currency_code' => 'EUR', 'quote_currency_code' => 'USD', 'rate' => '1.2', 'effective_at' => '2026-10-20T00:00:00Z']);

    $result = app(ExchangeRateService::class)->convert($actor, $team, '10', 'EUR', 'USD', CarbonImmutable::parse('2026-10-25T00:00:00Z'));

    expect($result->convertedAmount)->toBe('12.00000000');
});

test('conversion rejects non decimal amount inputs and unknown currency records', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $at = CarbonImmutable::parse('2026-10-25T00:00:00Z');

    expect(fn () => app(ExchangeRateService::class)->convert($actor, $team, 1.25, 'USD', 'USD', $at))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(ExchangeRateService::class)->convert($actor, $team, '1e2', 'USD', 'USD', $at))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(ExchangeRateService::class)->convert($actor, $team, '1', 'ZZZ', 'ZZZ', $at))->toThrow(ValidationException::class);
});

test('manual FX recording is authorized and assigns source actor and recording time on the server', function (TeamRole $role) {
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(12, 0, 0, 123456));
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($actor, ['role' => $role->value]);

    $rate = app(RecordExchangeRate::class)->handle($actor, $team, ['base_currency_code' => 'USD', 'quote_currency_code' => 'INR', 'rate' => '83.25', 'effective_at' => '2026-10-20T00:00:00Z']);

    expect($rate->team_id)->toBe($team->id);
    expect($rate->source)->toBe('manual');
    expect($rate->created_by)->toBe($actor->id);
    expect($rate->recorded_at->toISOString())->toBe('2026-10-25T12:00:00.123456Z');
})->with([TeamRole::Owner, TeamRole::Admin]);

test('ordinary members cannot record manual rates', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($actor, ['role' => TeamRole::Member->value]);

    expect(fn () => app(RecordExchangeRate::class)->handle($actor, $team, ['base_currency_code' => 'USD', 'quote_currency_code' => 'INR', 'rate' => '83', 'effective_at' => '2026-10-20T00:00:00Z']))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('exchange_rates', 0);
});

test('rates in another household cannot fill a missing pair or authorize outsider conversions', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $other = User::factory()->create()->personalTeam();
    ExchangeRate::factory()->create(['team_id' => $other->id, 'effective_at' => '2026-10-20T00:00:00Z']);
    $at = CarbonImmutable::parse('2026-10-25T00:00:00Z');

    expect(fn () => app(ExchangeRateService::class)->convert($actor, $team, '1', 'USD', 'INR', $at))->toThrow(MissingExchangeRate::class);
    expect(fn () => app(ExchangeRateService::class)->convert($actor, $other, '1', 'USD', 'USD', $at))->toThrow(AuthorizationException::class);
});

test('FX observations cannot be edited deleted or moved between households', function () {
    $row = ExchangeRate::factory()->create();

    expect(fn () => $row->update(['rate' => '84']))->toThrow(DomainException::class);
    expect(fn () => $row->delete())->toThrow(DomainException::class);
    $this->assertModelExists($row);
});

test('currency case aliases cannot reverse direct pair semantics or create artificial identity rates', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $rate = app(RecordExchangeRate::class)->handle($actor, $team, ['base_currency_code' => 'usd', 'quote_currency_code' => 'inr', 'rate' => '80', 'effective_at' => '2026-10-20T00:00:00Z']);

    $result = app(ExchangeRateService::class)->convert($actor, $team, '1', 'usd', 'inr', CarbonImmutable::parse('2026-10-25T00:00:00Z'));

    expect($result->convertedAmount)->toBe('80.00000000');
    expect($result->quote->isInverse)->toBeFalse();
    expect($rate->base_currency_code)->toBe('USD');
    expect(fn () => app(RecordExchangeRate::class)->handle($actor, $team, ['base_currency_code' => 'usd', 'quote_currency_code' => 'USD', 'rate' => '2', 'effective_at' => '2026-10-20T00:00:00Z']))->toThrow(ValidationException::class);
});

test('manual FX writes reject invalid values and client supplied tenant or audit attributes', function (string $field, mixed $value) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $input = ['base_currency_code' => 'USD', 'quote_currency_code' => 'INR', 'rate' => '80', 'effective_at' => '2026-10-20T00:00:00Z'];
    $input[$field] = $value;

    expect(fn () => app(RecordExchangeRate::class)->handle($actor, $team, $input))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('exchange_rates', 0);
})->with([
    ['rate', '0'], ['rate', '-1'], ['rate', 80.25],
    ['base_currency_code', 'ZZZ'], ['quote_currency_code', 'USD'],
    ['effective_at', '2026-02-30T00:00:00Z'], ['effective_at', '2026-10-20'],
    ['team_id', 123], ['source', 'untrusted'], ['recorded_at', '2026-10-20T00:00:00Z'], ['created_by', 123],
]);
