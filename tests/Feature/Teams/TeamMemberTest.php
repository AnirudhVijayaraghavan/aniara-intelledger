<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

test('team member roles can be updated by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Admin->value,
        ]);

    $response->assertRedirect(route('teams.edit', $team));

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role->value)->toEqual(TeamRole::Admin->value);
});

test('team member roles cannot be updated by non owners', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($admin)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Admin->value,
        ]);

    $response->assertForbidden();
});

test('team members can be removed by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $member]));

    $response->assertRedirect(route('teams.edit', $team));

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('team members cannot be removed by non owners', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($admin)
        ->delete(route('teams.members.destroy', [$team, $member]));

    $response->assertForbidden();
});

test('team owner cannot be removed', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $owner]));

    $response->assertForbidden();

    expect($owner->fresh()->belongsToTeam($team))->toBeTrue();
});

test('team member role cannot be set to owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Owner->value,
        ]);

    $response->assertSessionHasErrors('role');

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role->value)->toEqual(TeamRole::Member->value);
});

test('removed member current team is set to personal team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $personalTeam = $member->personalTeam();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $member->update(['current_team_id' => $team->id]);

    $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $member]));

    expect($member->fresh()->current_team_id)->toEqual($personalTeam->id);
});

test('the last owner cannot demote themselves through a regular role update', function (TeamRole $role) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();

    $response = $this->actingAs($owner)->patch(route('teams.members.update', [$team, $owner]), [
        'role' => $role->value,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $owner->id,
        'role' => TeamRole::Owner->value,
    ]);
})->with([TeamRole::Admin, TeamRole::Member]);

test('regular role updates cannot demote another existing owner', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($otherOwner, ['role' => TeamRole::Owner->value]);

    $response = $this->actingAs($owner)->patch(route('teams.members.update', [$team, $otherOwner]), [
        'role' => TeamRole::Member->value,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $otherOwner->id,
        'role' => TeamRole::Owner->value,
    ]);
});

test('member removal protects every owner instead of only the first owner', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($otherOwner, ['role' => TeamRole::Owner->value]);

    $response = $this->actingAs($owner)->delete(route('teams.members.destroy', [$team, $otherOwner]));

    $response->assertForbidden();
    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $otherOwner->id,
        'role' => TeamRole::Owner->value,
    ]);
});

test('owners can demote administrators to ordinary members', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

    $response = $this->actingAs($owner)->patch(route('teams.members.update', [$team, $admin]), [
        'role' => TeamRole::Member->value,
    ]);

    $response->assertRedirect(route('teams.edit', $team));
    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $admin->id,
        'role' => TeamRole::Member->value,
    ]);
});

test('membership updates cannot change a user in another team', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $team = $owner->personalTeam();

    $response = $this->actingAs($owner)->patch(route('teams.members.update', [$team, $outsider]), [
        'role' => TeamRole::Admin->value,
    ]);

    $response->assertNotFound();
    $this->assertDatabaseMissing('team_members', ['team_id' => $team->id, 'user_id' => $outsider->id]);
    expect($outsider->fresh()->ownsTeam($outsider->personalTeam()))->toBeTrue();
});
