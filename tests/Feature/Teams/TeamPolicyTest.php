<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('member management policies require ownership and protect owner memberships', function (
    ?TeamRole $actorRole,
    TeamRole $memberRole,
    bool $allowed,
    string $ability,
) {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->personalTeam();

    if ($actorRole !== null) {
        $team->members()->attach($actor, ['role' => $actorRole->value]);
    }

    $team->members()->attach($member, ['role' => $memberRole->value]);

    expect(Gate::forUser($actor)->allows($ability, [$team, $member]))->toBe($allowed);
})->with([
    'owner manages member' => [TeamRole::Owner, TeamRole::Member, true],
    'owner manages admin' => [TeamRole::Owner, TeamRole::Admin, true],
    'owner manages owner' => [TeamRole::Owner, TeamRole::Owner, false],
    'admin manages member' => [TeamRole::Admin, TeamRole::Member, false],
    'admin manages admin' => [TeamRole::Admin, TeamRole::Admin, false],
    'admin manages owner' => [TeamRole::Admin, TeamRole::Owner, false],
    'member manages member' => [TeamRole::Member, TeamRole::Member, false],
    'member manages admin' => [TeamRole::Member, TeamRole::Admin, false],
    'member manages owner' => [TeamRole::Member, TeamRole::Owner, false],
    'outsider manages member' => [null, TeamRole::Member, false],
    'outsider manages admin' => [null, TeamRole::Admin, false],
    'outsider manages owner' => [null, TeamRole::Owner, false],
])->with(['updateMember', 'removeMember']);

test('strict enum membership preserves each role permission set', function (TeamRole $role, array $expected) {
    $actual = [];
    foreach (TeamPermission::cases() as $permission) {
        $actual[$permission->value] = $role->hasPermission($permission);
    }

    expect($actual)->toBe($expected);
})->with([
    'owner' => [TeamRole::Owner, ['team:update' => true, 'team:delete' => true, 'member:add' => true,
        'member:update' => true, 'member:remove' => true, 'invitation:create' => true, 'invitation:cancel' => true]],
    'admin' => [TeamRole::Admin, ['team:update' => true, 'team:delete' => false, 'member:add' => false,
        'member:update' => false, 'member:remove' => false, 'invitation:create' => true, 'invitation:cancel' => true]],
    'member' => [TeamRole::Member, ['team:update' => false, 'team:delete' => false, 'member:add' => false,
        'member:update' => false, 'member:remove' => false, 'invitation:create' => false, 'invitation:cancel' => false]],
]);
