<?php

use App\Enums\TeamRole;
use App\Models\ExchangeRate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, string> */
function manualFxInput(): array
{
    return ['base_currency_code' => 'USD', 'quote_currency_code' => 'INR', 'rate' => '83.123456789012345678', 'effective_at' => '2026-10-20T12:00:00Z'];
}

test('FX management has an empty state and registered currency choices for an explicit team', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();

    $this->actingAs($actor)->get(route('exchange-rates.index', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('exchange-rates/index')
        ->where('team.id', $team->id)->has('rates.data', 0)->has('currencies', 2)
        ->where('canRecord', true)->where('defaults.base_currency_code', 'USD')->where('defaults.quote_currency_code', 'INR'));
});

test('owners and admins record exact manual rates through the team route', function (TeamRole $role) {
    $this->travelTo(CarbonImmutable::parse('2026-10-25T12:00:00.123456Z'));
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($actor, ['role' => $role->value]);

    $this->actingAs($actor)->post(route('exchange-rates.store', ['current_team' => $team->slug]), manualFxInput())
        ->assertSessionHasNoErrors()->assertRedirectToRoute('exchange-rates.index', ['current_team' => $team->slug]);

    $rate = ExchangeRate::sole();
    expect($rate->team_id)->toBe($team->id);
    expect($rate->rate)->toBe('83.123456789012345678');
    expect($rate->source)->toBe('manual');
    expect($rate->created_by)->toBe($actor->id);
    expect($rate->recorded_at->toISOString())->toBe('2026-10-25T12:00:00.123456Z');
})->with([TeamRole::Owner, TeamRole::Admin]);

test('FX corrections append observations and never rewrite prior rates', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-25T12:00:00Z'));
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $url = route('exchange-rates.store', ['current_team' => $team->slug]);
    $this->actingAs($actor)->post($url, manualFxInput())->assertSessionHasNoErrors();
    $first = ExchangeRate::sole();
    $this->travelTo(CarbonImmutable::parse('2026-10-25T12:00:01Z'));

    $this->post($url, array_replace(manualFxInput(), ['rate' => '84']))->assertSessionHasNoErrors();

    $this->assertDatabaseCount('exchange_rates', 2);
    expect($first->fresh()->rate)->toBe('83.123456789012345678');
    expect(ExchangeRate::orderByDesc('recorded_at')->firstOrFail()->rate)->toBe('84.000000000000000000');
});

test('duplicate FX timestamps return field validation feedback without changing history', function () {
    $this->freezeTime();
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $url = route('exchange-rates.store', ['current_team' => $team->slug]);
    $this->actingAs($actor)->post($url, manualFxInput())->assertSessionHasNoErrors();

    $this->postJson($url, manualFxInput())->assertUnprocessable()->assertJsonValidationErrors('rate');
    $this->assertDatabaseCount('exchange_rates', 1);
});

test('FX history is isolated and ordered by effective recording time and id', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $older = ExchangeRate::factory()->create(['team_id' => $team->id, 'effective_at' => '2026-10-20T00:00:00Z', 'recorded_at' => '2026-10-21T00:00:00Z']);
    $later = ExchangeRate::factory()->create(['team_id' => $team->id, 'effective_at' => '2026-10-22T00:00:00Z', 'recorded_at' => '2026-10-23T00:00:00Z']);
    $correction = ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => '84.123456789012345678', 'effective_at' => '2026-10-22T00:00:00Z', 'recorded_at' => '2026-10-24T00:00:00Z']);
    ExchangeRate::factory()->create();

    $this->actingAs($actor)->get(route('exchange-rates.index', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('rates.data', 3)
        ->where('rates.data.0.id', $correction->id)->where('rates.data.0.rate', '84.123456789012345678')
        ->where('rates.data.0.source', 'manual')->where('rates.data.0.baseCurrencyCode', 'USD')
        ->where('rates.data.0.quoteCurrencyCode', 'INR')->where('rates.data.0.effectiveAt', '2026-10-22T00:00:00.000000Z')
        ->where('rates.data.1.id', $later->id)->where('rates.data.2.id', $older->id));
});

test('FX history is paginated and missing-rate links prefill an eligible currency pair', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    ExchangeRate::factory()->count(26)->sequence(fn (Sequence $sequence) => [
        'recorded_at' => CarbonImmutable::parse('2026-10-25T12:00:00Z')->addSeconds($sequence->index),
    ])->create(['team_id' => $team->id, 'effective_at' => '2026-10-20T00:00:00Z']);

    $this->actingAs($actor)->get(route('exchange-rates.index', ['current_team' => $team->slug, 'base_currency_code' => 'inr', 'quote_currency_code' => 'usd', 'page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('rates.data', 1)->where('rates.total', 26)
        ->where('rates.current_page', 2)->where('defaults.base_currency_code', 'INR')->where('defaults.quote_currency_code', 'USD'));
});

test('ordinary members can view FX history but cannot submit rates', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($actor, ['role' => TeamRole::Member->value]);

    $this->actingAs($actor)->get(route('exchange-rates.index', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canRecord', false));
    $this->post(route('exchange-rates.store', ['current_team' => $team->slug]), manualFxInput())->assertForbidden();
    $this->assertDatabaseCount('exchange_rates', 0);
});

test('outsiders and revoked members cannot use a current team preference to access FX management', function (bool $revoked) {
    $actor = User::factory()->create();
    $team = User::factory()->create()->personalTeam();
    if ($revoked) {
        $team->members()->attach($actor, ['role' => TeamRole::Admin->value]);
        $team->members()->detach($actor);
    }
    $actor->update(['current_team_id' => $team->id]);

    $this->actingAs($actor)->get(route('exchange-rates.index', ['current_team' => $team->slug]))->assertForbidden();
    $this->post(route('exchange-rates.store', ['current_team' => $team->slug]), manualFxInput())->assertForbidden();
})->with([false, true]);

test('guests cannot view or record household FX rates', function () {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();

    $this->get(route('exchange-rates.index', ['current_team' => $team->slug]))->assertRedirectToRoute('login');
    $this->post(route('exchange-rates.store', ['current_team' => $team->slug]), manualFxInput())->assertRedirectToRoute('login');
});

test('FX form requests reject invalid rates currencies pairs dates and protected attributes', function (string $field, mixed $value) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();

    $this->actingAs($actor)->postJson(route('exchange-rates.store', ['current_team' => $team->slug]), array_replace(manualFxInput(), [$field => $value]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('exchange_rates', 0);
})->with([
    ['rate', '0'], ['rate', '-1'], ['rate', 83.25], ['rate', '0.1234567890123456789'],
    ['base_currency_code', 'ZZZ'], ['quote_currency_code', 'usd'],
    ['effective_at', '2026-02-30T00:00:00Z'], ['effective_at', '2026-10-20T12:00:00'],
    ['team_id', 123], ['source', 'untrusted'], ['recorded_at', '2026-10-20T00:00:00Z'], ['created_by', 123],
]);

test('FX HTTP routes never expose updates or deletion', function (string $method) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $rate = ExchangeRate::factory()->create(['team_id' => $team->id]);

    $this->actingAs($actor)->call($method, route('exchange-rates.store', ['current_team' => $team->slug]), manualFxInput())->assertMethodNotAllowed();
    expect($rate->fresh()->rate)->toBe('83.000000000000000000');
})->with(['PUT', 'PATCH', 'DELETE']);
