<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Requests\Teams\CreateTeamInvitationRequest;
use App\Http\Requests\Teams\RespondToTeamInvitationRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

function invitationResponseRequest(TeamInvitation $invitation, mixed $principal, string $operation = 'accept'): RespondToTeamInvitationRequest
{
    $suffix = $operation === 'accept' ? '/accept' : '';
    $method = $operation === 'accept' ? 'POST' : 'DELETE';
    $request = RespondToTeamInvitationRequest::create('/invitations/'.$invitation->code.$suffix, $method);
    $route = new Route($method, 'invitations/{invitation}'.$suffix, fn () => null);
    $route->bind($request);
    $route->setParameter('invitation', $invitation);
    $request->setRouteResolver(fn () => $route);
    $request->setUserResolver(fn () => $principal);
    $request->setContainer(app())->setRedirector(app('redirect'));

    return $request;
}

function invitationCreationRequest(Team $team, mixed $principal): CreateTeamInvitationRequest
{
    $request = CreateTeamInvitationRequest::create('/settings/teams/'.$team->slug.'/invitations', 'POST', [
        'email' => 'new-invite@example.test', 'role' => TeamRole::Member->value,
    ]);
    $route = new Route('POST', 'settings/teams/{team}/invitations', fn () => null);
    $route->bind($request);
    $route->setParameter('team', $team);
    $request->setRouteResolver(fn () => $route);
    $request->setUserResolver(fn () => $principal);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    return $request;
}

test('all invitation controller mutations reject missing or incompatible request principals despite a valid global user', function (string $operation, bool $missing) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $invitee = User::factory()->create();
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => $invitee->email, 'invited_by' => $owner->id]);
    $before = $invitation->fresh()->getAttributes();
    $globalUser = in_array($operation, ['accept', 'decline'], true) ? $invitee : $owner;
    $this->actingAs($globalUser);
    Notification::fake();
    $principal = $missing ? null : new GenericUser(['id' => $globalUser->id, 'email' => $globalUser->email]);
    $request = match ($operation) {
        'store' => new CreateTeamInvitationRequest,
        'destroy' => new Request,
        default => new RespondToTeamInvitationRequest,
    };
    $request->setUserResolver(fn () => $principal);
    $controller = app(TeamInvitationController::class);
    $call = match ($operation) {
        'store' => fn () => $controller->store($request, $team),
        'destroy' => fn () => $controller->destroy($request, $team, $invitation),
        'accept' => fn () => $controller->accept($request, $invitation),
        'decline' => fn () => $controller->decline($request, $invitation),
    };

    expect($call)->toThrow(AuthenticationException::class, 'Unauthenticated.');
    expect($invitation->fresh()->getAttributes())->toBe($before);
    expect($team->memberships()->count())->toBe(1);
    $this->assertDatabaseCount('team_invitations', 1);
    Notification::assertNothingSent();
})->with(['store', 'destroy', 'accept', 'decline'])->with([false, true]);

test('response form validation rejects wrong principal types before constructing eligibility rules', function (bool $missing) {
    $invitee = User::factory()->create();
    $invitation = TeamInvitation::factory()->create(['email' => $invitee->email]);
    $this->actingAs($invitee);
    $principal = $missing ? null : new GenericUser(['id' => $invitee->id, 'email' => $invitee->email]);
    $request = invitationResponseRequest($invitation, $principal);

    expect(fn () => $request->validateResolved())->toThrow(AuthenticationException::class, 'Unauthenticated.');
    expect($invitation->fresh()->accepted_at)->toBeNull();
    $this->assertModelExists($invitation);
})->with([false, true]);

test('invitation management authorizes the request principal instead of the privileged global owner', function (string $operation, bool $member) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $actor = User::factory()->create();
    if ($member) {
        $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    }
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'invited_by' => $owner->id]);
    $before = $invitation->fresh()->getAttributes();
    $this->actingAs($owner);
    Notification::fake();
    $controller = app(TeamInvitationController::class);
    if ($operation === 'store') {
        $request = invitationCreationRequest($team, $actor);
        $call = fn () => $controller->store($request, $team);
    } else {
        $request = new Request;
        $request->setUserResolver(fn () => $actor);
        $call = fn () => $controller->destroy($request, $team, $invitation);
    }

    expect($call)->toThrow(AuthorizationException::class);
    expect($invitation->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('team_invitations', 1);
    Notification::assertNothingSent();
})->with(['store', 'destroy'])->with([false, true]);

test('invitation creation records and authorizes the request administrator while the global guard belongs elsewhere', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-27T12:00:00Z'));
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $this->actingAs(User::factory()->create());
    Notification::fake();
    $request = invitationCreationRequest($team, $admin);

    $response = app(TeamInvitationController::class)->store($request, $team);

    expect($response->getTargetUrl())->toBe(route('teams.edit', $team));
    $invitation = $team->invitations()->sole();
    expect($invitation->invited_by)->toBe($admin->id);
    expect($invitation->role)->toBe(TeamRole::Member);
    expect($invitation->expires_at->format('Y-m-d H:i:s'))->toBe('2026-10-30 12:00:00');
    expect($owner->fresh()->ownsTeam($team))->toBeTrue();
    Notification::assertSentOnDemand(TeamInvitationNotification::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'new-invite@example.test');
});

test('invitation responses use the eligible request identity despite an unrelated globally authenticated user', function (string $operation) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $invitee = User::factory()->create();
    $globalUser = User::factory()->create();
    $globalTeamId = $globalUser->current_team_id;
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => strtoupper($invitee->email), 'invited_by' => $owner->id]);
    $this->actingAs($globalUser);
    URL::defaults(['current_team' => $globalUser->personalTeam()->slug]);
    $request = invitationResponseRequest($invitation, $invitee, $operation);
    $request->validateResolved();

    $response = app(TeamInvitationController::class)->{$operation}($request, $invitation);

    expect($response->getTargetUrl())->toBe(route('dashboard'));
    expect($globalUser->fresh()->current_team_id)->toBe($globalTeamId);
    expect($globalUser->fresh()->belongsToTeam($team))->toBeFalse();
    expect($owner->fresh()->ownsTeam($team))->toBeTrue();
    if ($operation === 'accept') {
        expect($invitee->fresh()->teamRole($team))->toBe(TeamRole::Member);
        expect($invitee->fresh()->current_team_id)->toBe($team->id);
        expect($invitation->fresh()->accepted_at)->not->toBeNull();
    } else {
        $this->assertModelMissing($invitation);
        expect($invitee->fresh()->belongsToTeam($team))->toBeFalse();
    }
})->with(['accept', 'decline']);

test('response eligibility checks the request email instead of the eligible global user', function (string $operation) {
    $invitee = User::factory()->create();
    $outsider = User::factory()->create();
    $invitation = TeamInvitation::factory()->create(['email' => $invitee->email]);
    $before = $invitation->fresh()->getAttributes();
    $this->actingAs($invitee);
    $request = invitationResponseRequest($invitation, $outsider, $operation);

    expect(fn () => $request->validateResolved())
        ->toThrow(ValidationException::class, 'This invitation was sent to a different email address.');
    expect($invitation->fresh()->getAttributes())->toBe($before);
    expect($outsider->fresh()->belongsToTeam($invitation->team))->toBeFalse();
})->with(['accept', 'decline']);

test('invitation cancellation uses the authorized request owner despite an unrelated global guard', function () {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'invited_by' => $owner->id]);
    $this->actingAs(User::factory()->create());
    $request = new Request;
    $request->setUserResolver(fn () => $owner);

    $response = app(TeamInvitationController::class)->destroy($request, $team, $invitation);

    expect($response->getTargetUrl())->toBe(route('teams.edit', $team));
    $this->assertModelMissing($invitation);
    expect($owner->fresh()->ownsTeam($team))->toBeTrue();
});

test('household members cannot revoke invitations through the management endpoint', function () {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'invited_by' => $owner->id]);
    $before = $invitation->fresh()->getAttributes();

    $this->actingAs($member)->deleteJson(route('teams.invitations.destroy', ['team' => $team->slug, 'invitation' => $invitation->code]))->assertForbidden();

    expect($invitation->fresh()->getAttributes())->toBe($before);
});

test('membership in two households cannot authorize revoking an invitation through the wrong household route', function () {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $other = User::factory()->create()->personalTeam();
    $other->members()->attach($owner, ['role' => TeamRole::Admin->value]);
    $invitation = TeamInvitation::factory()->create(['team_id' => $other->id]);
    $before = $invitation->fresh()->getAttributes();

    $this->actingAs($owner)->deleteJson(route('teams.invitations.destroy', ['team' => $team->slug, 'invitation' => $invitation->code]))->assertNotFound();

    expect($invitation->fresh()->getAttributes())->toBe($before);
});

test('invitation endpoints retain guest redirects and JSON authentication errors without side effects', function (string $operation, bool $json) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'invited_by' => $owner->id]);
    $before = $invitation->fresh()->getAttributes();
    Notification::fake();
    $route = match ($operation) {
        'store', 'destroy' => route('teams.invitations.'.$operation, ['team' => $team->slug, 'invitation' => $invitation->code]),
        default => route('invitations.'.$operation, $invitation),
    };
    $method = (in_array($operation, ['store', 'accept'], true) ? 'post' : 'delete').($json ? 'Json' : '');

    $response = $this->{$method}($route, ['email' => 'new-invite@example.test', 'role' => 'member']);

    if ($json) {
        $response->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    } else {
        $response->assertRedirectToRoute('login');
    }
    expect($invitation->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('team_invitations', 1);
    Notification::assertNothingSent();
})->with(['store', 'destroy', 'accept', 'decline'])->with([false, true]);

test('unknown invitation codes return 404 for authenticated responses without altering other invitations', function (string $operation) {
    $invitee = User::factory()->create();
    $invitation = TeamInvitation::factory()->create(['email' => $invitee->email]);
    $before = $invitation->fresh()->getAttributes();
    $method = $operation === 'accept' ? 'postJson' : 'deleteJson';

    $this->actingAs($invitee)->{$method}(route('invitations.'.$operation, ['invitation' => 'unknown-code']))->assertNotFound();

    expect($invitation->fresh()->getAttributes())->toBe($before);
    expect($invitee->fresh()->belongsToTeam($invitation->team))->toBeFalse();
})->with(['accept', 'decline']);

test('repeated invitation operations retain their existing lifecycle and membership outcomes', function (string $operation) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $invitee = User::factory()->create();
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => $invitee->email, 'invited_by' => $owner->id]);
    $url = $operation === 'destroy'
        ? route('teams.invitations.destroy', ['team' => $team->slug, 'invitation' => $invitation->code])
        : route('invitations.'.$operation, $invitation);
    $method = $operation === 'accept' ? 'postJson' : 'deleteJson';
    $this->actingAs($operation === 'destroy' ? $owner : $invitee)->{$method}($url)->assertRedirect();

    $response = $this->{$method}($url);

    if ($operation === 'accept') {
        $response->assertUnprocessable()->assertJsonValidationErrors('invitation')
            ->assertJsonPath('errors.invitation.0', 'This invitation has already been accepted.');
        expect($team->memberships()->where('user_id', $invitee->id)->count())->toBe(1);
        expect($invitee->fresh()->teamRole($team))->toBe(TeamRole::Member);
    } else {
        $response->assertNotFound();
        $this->assertModelMissing($invitation);
        expect($invitee->fresh()->belongsToTeam($team))->toBeFalse();
    }
    expect($owner->fresh()->ownsTeam($team))->toBeTrue();
})->with(['accept', 'decline', 'destroy']);

test('an expired invitation cannot be declined and remains intact', function () {
    $invitee = User::factory()->create();
    $invitation = TeamInvitation::factory()->expired()->create(['email' => $invitee->email]);
    $before = $invitation->fresh()->getAttributes();

    $this->actingAs($invitee)->deleteJson(route('invitations.decline', $invitation))->assertUnprocessable()
        ->assertJsonPath('errors.invitation.0', 'This invitation has expired.');

    expect($invitation->fresh()->getAttributes())->toBe($before);
});

test('acceptance preserves existing membership permissions rather than applying an invitation role', function () {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => $member->email, 'role' => TeamRole::Admin, 'invited_by' => $owner->id]);

    $this->actingAs($member)->post(route('invitations.accept', $invitation))->assertRedirect();

    expect($member->fresh()->teamRole($team))->toBe(TeamRole::Member);
    expect($team->memberships()->where('user_id', $member->id)->count())->toBe(1);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
    expect($owner->fresh()->ownsTeam($team))->toBeTrue();
});

test('acceptance revalidates persisted eligibility under its existing locks instead of trusting a validated stale invitation', function () {
    $invitee = User::factory()->create();
    $invitation = TeamInvitation::factory()->create(['email' => $invitee->email]);
    $request = invitationResponseRequest($invitation, $invitee);
    $request->validateResolved();
    $invitation->fresh()->update(['expires_at' => now()->subDay()]);
    $before = $invitation->fresh()->getAttributes();

    expect(fn () => app(TeamInvitationController::class)->accept($request, $invitation))
        ->toThrow(ValidationException::class, 'This invitation has expired.');

    expect($invitation->fresh()->getAttributes())->toBe($before);
    expect($invitee->fresh()->belongsToTeam($invitation->team))->toBeFalse();
});
