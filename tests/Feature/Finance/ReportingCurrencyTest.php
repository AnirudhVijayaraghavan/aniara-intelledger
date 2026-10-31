<?php

use App\Actions\Finance\UpdateTeamReportingCurrency;
use App\Enums\TeamRole;
use App\Models\Currency;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Inertia\Testing\AssertableInertia as Assert;

test('teams default to USD and reference an existing reporting currency', function () {
    $team = Team::factory()->create();

    expect($team->fresh()->reporting_currency_code)->toBe('USD');
    expect($team->reportingCurrency->code)->toBe('USD');
});

test('owners and administrators update the explicit teams reporting currency', function (TeamRole $role) {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($actor, ['role' => $role->value]);

    $this->actingAs($actor)->patchJson(route('teams.reporting-currency.update', ['team' => $team->slug]), ['reporting_currency_code' => 'INR'])->assertNoContent();

    expect($team->fresh()->reporting_currency_code)->toBe('INR');
    expect($actor->personalTeam()->fresh()->reporting_currency_code)->toBe('USD');
})->with([TeamRole::Owner, TeamRole::Admin]);

test('ordinary members cannot update reporting currency through HTTP or the action', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($actor, ['role' => TeamRole::Member->value]);

    $this->actingAs($actor)->patchJson(route('teams.reporting-currency.update', ['team' => $team->slug]), ['reporting_currency_code' => 'INR'])->assertForbidden();
    expect(fn () => app(UpdateTeamReportingCurrency::class)->handle($actor, $team, ['reporting_currency_code' => 'INR']))->toThrow(AuthorizationException::class);
    expect($team->fresh()->reporting_currency_code)->toBe('USD');
});

test('outsiders and revoked members cannot rely on current team preferences to modify reporting currency', function (bool $revoked) {
    $actor = User::factory()->create();
    $team = User::factory()->create()->personalTeam();
    if ($revoked) {
        $team->members()->attach($actor, ['role' => TeamRole::Admin->value]);
        $team->members()->detach($actor);
    }
    $actor->update(['current_team_id' => $team->id]);

    $this->actingAs($actor)->patchJson(route('teams.reporting-currency.update', ['team' => $team->slug]), ['reporting_currency_code' => 'INR'])->assertForbidden();
    expect($team->fresh()->reporting_currency_code)->toBe('USD');
})->with([false, true]);

test('reporting currency validates initially supported codes even when other currencies exist', function (mixed $value) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    Currency::factory()->create(['code' => 'EUR']);

    $this->actingAs($actor)->patchJson(route('teams.reporting-currency.update', ['team' => $team->slug]), ['reporting_currency_code' => $value])
        ->assertUnprocessable()->assertJsonValidationErrors('reporting_currency_code');
    expect($team->fresh()->reporting_currency_code)->toBe('USD');
})->with(['EUR', 'ZZZ', 'usd', null]);

test('a submitted tenant id cannot redirect reporting currency changes', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $other = User::factory()->create()->personalTeam();

    $this->actingAs($actor)->patchJson(route('teams.reporting-currency.update', ['team' => $team->slug]), ['reporting_currency_code' => 'INR', 'team_id' => $other->id])
        ->assertUnprocessable()->assertJsonValidationErrors('team_id');
    expect($team->fresh()->reporting_currency_code)->toBe('USD');
    expect($other->fresh()->reporting_currency_code)->toBe('USD');
});

test('guests and retired teams cannot update financial reporting settings', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $url = route('teams.reporting-currency.update', ['team' => $team->slug]);
    $this->patch($url, ['reporting_currency_code' => 'INR'])->assertRedirectToRoute('login');
    $team->delete();

    $this->actingAs($actor)->patchJson($url, ['reporting_currency_code' => 'INR'])->assertNotFound();
});

test('team settings expose the reporting currency and role specific controls', function (TeamRole $role, bool $canUpdate) {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($actor, ['role' => $role->value]);

    $this->actingAs($actor)->get(route('teams.edit', ['team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('teams/edit')
        ->where('team.reportingCurrencyCode', 'USD')->where('canUpdateReportingCurrency', $canUpdate)
        ->has('reportingCurrencies', 2)->where('reportingCurrencies.0.code', 'INR')->where('reportingCurrencies.1.code', 'USD'));
})->with([[TeamRole::Owner, true], [TeamRole::Admin, true], [TeamRole::Member, false]]);

test('reporting currency changes support Inertia redirects while retaining the JSON endpoint contract', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();

    $this->actingAs($actor)->withHeader('X-Inertia', 'true')->patch(route('teams.reporting-currency.update', ['team' => $team->slug]), ['reporting_currency_code' => 'INR'])
        ->assertStatus(303)->assertRedirectToRoute('teams.edit', ['team' => $team->slug]);
    expect($team->fresh()->reporting_currency_code)->toBe('INR');
    $this->withoutHeader('X-Inertia')->get(route('teams.edit', ['team' => $team->slug]))->assertInertia(fn (Assert $page) => $page->where('team.reportingCurrencyCode', 'INR'));
});

test('reporting currency forms receive field validation errors without changing settings', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $settings = route('teams.edit', ['team' => $team->slug]);

    $this->actingAs($actor)->from($settings)->withHeader('X-Inertia', 'true')
        ->patch(route('teams.reporting-currency.update', ['team' => $team->slug]), ['reporting_currency_code' => 'ZZZ'])
        ->assertRedirect($settings)->assertSessionHasErrors('reporting_currency_code');
    expect($team->fresh()->reporting_currency_code)->toBe('USD');
});
