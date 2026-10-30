<?php

declare(strict_types=1);

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamMemberController;
use App\Http\Requests\Teams\DeleteTeamRequest;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** @return array{User, Team, User} */
function teamContractContext(): array
{
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    return [$owner, $team, $member];
}

/** @return array<string, string> */
function teamContractState(): array
{
    $state = [];
    foreach (['users', 'teams', 'team_members', 'team_invitations'] as $table) {
        $state[$table] = hash('sha256', DB::table($table)->orderBy('id')->get()->toJson());
    }

    return $state;
}

function teamContractCall(string $operation, mixed $principal, Team $team, User $member, bool $validate = true): Closure
{
    $input = ['name' => $operation === 'destroy' ? $team->name : 'Contract household', 'role' => TeamRole::Admin->value];
    $requestClass = match ($operation) {
        'store', 'update' => SaveTeamRequest::class,
        'destroy' => DeleteTeamRequest::class,
        'members.update' => UpdateTeamMemberRequest::class,
        default => Request::class,
    };
    $request = new $requestClass($input);
    $request->headers->set('X-Inertia', 'true');
    $request->setUserResolver(fn () => $principal);
    $route = new Route('GET', 'team-contract', fn () => null);
    $route->bind(Request::create('/team-contract'));
    $route->setParameter('team', $team);
    $request->setRouteResolver(fn () => $route);
    if ($validate && $request instanceof FormRequest) {
        $validator = Validator::make($request->all(), $request->rules());
        if ($request instanceof DeleteTeamRequest) {
            $validator->after($request->after());
        }
        $request->setValidator($validator);
    }
    $controller = app(TeamController::class);
    $call = match ($operation) {
        'index' => fn () => $controller->index($request),
        'store' => fn () => $controller->store($request, app(CreateTeam::class)),
        'edit', 'update', 'switch', 'leave', 'destroy' => fn () => $controller->{$operation}($request, $team),
        'members.update' => fn () => app(TeamMemberController::class)->update($request, $team, $member),
        'members.destroy' => fn () => app(TeamMemberController::class)->destroy($request, $team, $member),
    };

    return in_array($operation, ['index', 'edit'], true) ? fn () => $call()->toResponse($request) : $call;
}

test('team boundaries reject missing or incompatible request principals despite a valid global owner', function (string $operation, bool $missing) {
    [$owner, $team, $member] = teamContractContext();
    $this->actingAs($owner);
    $principal = $missing ? null : new GenericUser(['id' => $owner->id]);
    $before = teamContractState();

    expect(teamContractCall($operation, $principal, $team, $member, false))->toThrow(AuthenticationException::class, 'Unauthenticated.');

    expect(teamContractState())->toBe($before);
})->with(['index', 'store', 'edit', 'update', 'switch', 'leave', 'destroy', 'members.update', 'members.destroy'])->with([false, true]);

test('team mutations deny a request member or outsider despite a privileged global owner', function (string $operation, bool $isMember) {
    [$owner, $team, $target] = teamContractContext();
    $actor = User::factory()->create();
    if ($isMember) {
        $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    }
    $this->actingAs($owner);
    $before = teamContractState();

    expect(teamContractCall($operation, $actor, $team, $target))->toThrow(AuthorizationException::class);

    expect(teamContractState())->toBe($before);
})->with(['update', 'destroy', 'members.update', 'members.destroy'])->with([false, true]);

test('team read responses use the request member and their own permissions rather than the global owner', function () {
    [$owner, $team, $member] = teamContractContext();
    $this->actingAs($owner);

    $index = teamContractCall('index', $member, $team, $member)()->getData(true)['props'];
    $edit = teamContractCall('edit', $member, $team, $member)()->getData(true)['props'];

    expect(array_column($index['teams'], 'id'))->toContain($team->id, $member->personalTeam()->id)->not->toContain($owner->personalTeam()->id);
    expect($edit['permissions']['canUpdateTeam'])->toBeFalse();
    expect($edit['permissions']['canDeleteTeam'])->toBeFalse();
    expect($edit['canUpdateReportingCurrency'])->toBeFalse();
});

test('an outsider request cannot inspect or switch into a team despite an authorized global owner', function (string $operation) {
    [$owner, $team, $member] = teamContractContext();
    $this->actingAs($owner);
    $outsider = User::factory()->create();
    $before = teamContractState();

    expect(teamContractCall($operation, $outsider, $team, $member))->toThrow($operation === 'edit' ? AuthorizationException::class : HttpException::class);

    expect(teamContractState())->toBe($before);
})->with(['edit', 'switch']);

test('request owners retain team administration even when the global guard has no membership', function (string $operation) {
    [$owner, $team, $member] = teamContractContext();
    $globalUser = User::factory()->create();
    $globalBefore = $globalUser->fresh()->getAttributes();
    $this->actingAs($globalUser);

    teamContractCall($operation, $owner, $team, $member)();

    expect($globalUser->fresh()->getAttributes())->toBe($globalBefore);
    if ($operation === 'update') {
        expect($team->fresh()->name)->toBe('Contract household');
    } elseif ($operation === 'members.update') {
        expect($member->fresh()->teamRole($team))->toBe(TeamRole::Admin);
    } elseif ($operation === 'members.destroy') {
        expect($member->fresh()->belongsToTeam($team))->toBeFalse();
    } else {
        expect($team->fresh()->trashed())->toBeTrue();
    }
})->with(['update', 'members.update', 'members.destroy', 'destroy']);

test('team creation and switching use the explicit request user without changing the global user', function (string $operation) {
    [$owner, $team, $member] = teamContractContext();
    $this->actingAs($owner);
    $ownerBefore = $owner->fresh()->getAttributes();

    teamContractCall($operation, $member, $team, $member)();

    expect($owner->fresh()->getAttributes())->toBe($ownerBefore);
    if ($operation === 'store') {
        $created = Team::where('name', 'Contract household')->sole();
        expect($member->fresh()->ownsTeam($created))->toBeTrue();
        expect($member->fresh()->current_team_id)->toBe($created->id);
    } else {
        expect($member->fresh()->current_team_id)->toBe($team->id);
    }
})->with(['store', 'switch']);

test('request owners cannot leave a team even when the global member could leave', function () {
    [$owner, $team, $member] = teamContractContext();
    $this->actingAs($member);
    $before = teamContractState();

    expect(teamContractCall('leave', $owner, $team, $member))->toThrow(AuthorizationException::class);

    expect(teamContractState())->toBe($before);
});

test('request members leave only their own membership despite a globally authenticated owner', function () {
    [$owner, $team, $member] = teamContractContext();
    $member->switchTeam($team);
    $this->actingAs($owner);

    teamContractCall('leave', $member, $team, $member)();

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
    expect($member->fresh()->current_team_id)->toBe($member->personalTeam()->id);
    expect($owner->fresh()->ownsTeam($team))->toBeTrue();
});

test('team routes hide missing and cross team resources without changing memberships', function () {
    [$owner, $team, $member] = teamContractContext();
    $foreign = User::factory()->create();
    $before = teamContractState();

    $this->actingAs($owner)->getJson(route('teams.edit', ['team' => 'missing-team']))->assertNotFound();
    $this->getJson(route('teams.edit', ['team' => $foreign->personalTeam()->slug]))->assertForbidden();
    $this->deleteJson(route('teams.members.destroy', ['team' => $team->slug, 'user' => $foreign->id]))->assertNotFound();

    expect(teamContractState())->toBe($before);
});

test('team invitation display rejects absent or invalid required creation timestamps without changing invitations', function (?string $value) {
    [$owner, $team] = teamContractContext();
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'invited_by' => $owner->id]);
    $stored = DB::table('team_invitations')->where('id', $invitation->id)->value('created_at');
    Exceptions::fake();
    Event::listen('eloquent.retrieved: '.TeamInvitation::class, function (TeamInvitation $record) use ($value): void {
        $record->setRawAttributes(array_replace($record->getAttributes(), ['created_at' => $value]));
    });

    $this->actingAs($owner)->getJson(route('teams.edit', $team))->assertInternalServerError();

    Exceptions::assertReported(fn (UnexpectedValueException $exception): bool => $exception->getMessage() === 'Invitation creation timestamp must be valid.');
    expect(DB::table('team_invitations')->where('id', $invitation->id)->value('created_at'))->toBe($stored);
})->with([null, '0000-01-01 00:00:00']);

test('team invitation display preserves its existing UTC string contract', function () {
    [$owner, $team] = teamContractContext();
    TeamInvitation::factory()->create(['team_id' => $team->id, 'invited_by' => $owner->id, 'created_at' => '2026-10-01 12:34:56']);

    $this->actingAs($owner)->get(route('teams.edit', $team))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('invitations.0.created_at', '2026-10-01T12:34:56.000000Z'));
});
