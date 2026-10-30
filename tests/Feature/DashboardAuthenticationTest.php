<?php

declare(strict_types=1);

use App\Actions\Finance\ConsolidatedNetWorthCalculator;
use App\Enums\TeamRole;
use App\Http\Controllers\DashboardController;
use App\Models\AccountBalance;
use App\Models\FinancialAccount;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard rejects missing or incompatible request principals despite an authenticated global owner', function (bool $missing) {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $principal = $missing ? null : new GenericUser(['id' => $owner->id, 'email' => $owner->email]);
    $request = new Request;
    $request->setUserResolver(fn () => $principal);

    expect(fn () => app(DashboardController::class)($request, $owner->personalTeam(), app(ConsolidatedNetWorthCalculator::class)))
        ->toThrow(AuthenticationException::class, 'Unauthenticated.');
})->with([false, true]);

test('dashboard data and permission flags use the request member and explicit household instead of the privileged global owner', function () {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $preference = $member->current_team_id;
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '123.12345678', 'as_of_date' => now()->toDateString()]);
    $before = $balance->fresh()->getAttributes();
    $invitation = TeamInvitation::factory()->create(['email' => strtoupper($member->email)]);
    TeamInvitation::factory()->create(['email' => $owner->email]);
    $this->actingAs($owner);
    $request = Request::create('/'.$team->slug.'/dashboard', 'GET', [], [], [], ['HTTP_X_INERTIA' => 'true']);
    $request->setUserResolver(fn () => $member);

    $response = app(DashboardController::class)($request, $team, app(ConsolidatedNetWorthCalculator::class))->toResponse($request);
    $props = $response->getData(true)['props'];

    expect($props['team']['id'])->toBe($team->id);
    expect($props['financialOverview']['teamId'])->toBe($team->id);
    expect($props['financialOverview']['currencies']['USD']['netWorth'])->toBe('123.12345678');
    expect($props['consolidatedOverview']['netWorth'])->toBe('123.12345678');
    expect($props['canManageAccounts'])->toBeFalse();
    expect($props['canManageExchangeRates'])->toBeFalse();
    expect($props['pendingInvitations'])->toHaveCount(1);
    expect($props['pendingInvitations'][0]['code'])->toBe($invitation->code);
    expect($member->fresh()->current_team_id)->toBe($preference);
    expect($balance->fresh()->getAttributes())->toBe($before);
});

test('dashboard denies the request outsider before partial responses can skip financial authorization', function () {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $outsider = User::factory()->create();
    $this->actingAs($owner);
    $request = Request::create('/'.$team->slug.'/dashboard', 'GET', [], [], [], [
        'HTTP_X_INERTIA' => 'true', 'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'dashboard', 'HTTP_X_INERTIA_PARTIAL_DATA' => 'pendingInvitations,team',
    ]);
    $request->setUserResolver(fn () => $outsider);

    expect(fn () => app(DashboardController::class)($request, $team, app(ConsolidatedNetWorthCalculator::class)))
        ->toThrow(AuthorizationException::class);
});

test('dashboard JSON guests fail authentication while the public welcome page remains accessible', function () {
    $owner = User::factory()->create();

    $this->getJson(route('dashboard', ['current_team' => $owner->personalTeam()->slug]))
        ->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('welcome')->where('auth.user', null));
});
