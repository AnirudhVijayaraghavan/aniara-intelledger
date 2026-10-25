<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('the last owner of a non personal team cannot delete their account', function () {
    $owner = User::factory()->create();
    $personalTeam = $owner->personalTeam();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $personalTeam->id,
        'invited_by' => $owner->id,
    ]);

    $response = $this->actingAs($owner)->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'password']);

    $response->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors(['password' => 'You must transfer ownership or delete your shared teams before deleting your account.']);
    $this->assertAuthenticatedAs($owner);
    $this->assertModelExists($owner);
    $this->assertNotSoftDeleted($personalTeam);
    $this->assertNotSoftDeleted($team);
    $this->assertModelExists($invitation);
    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $owner->id,
        'role' => TeamRole::Owner->value,
    ]);
});

test('account deletion cannot orphan a personal team shared with another member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password']);

    $response->assertSessionHasErrors('password');
    $this->assertAuthenticatedAs($owner);
    $this->assertNotSoftDeleted($team);
    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $owner->id,
        'role' => TeamRole::Owner->value,
    ]);
});

test('account deletion retires an exclusively held personal team and its invitations', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'invited_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

    $response->assertRedirect(route('home'))->assertSessionHasNoErrors();
    $this->assertGuest();
    $this->assertModelMissing($user);
    $this->assertSoftDeleted($team);
    $this->assertModelMissing($invitation);
    $this->assertDatabaseMissing('team_members', ['team_id' => $team->id]);
});

test('a member can delete their account without deleting shared teams', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this->actingAs($member)->delete(route('profile.destroy'), ['password' => 'password']);

    $response->assertRedirect(route('home'))->assertSessionHasNoErrors();
    $this->assertModelMissing($member);
    $this->assertNotSoftDeleted($team);
    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $owner->id,
        'role' => TeamRole::Owner->value,
    ]);
});

test('deleting one co owner preserves the team and prevents the remaining owner from deleting their account', function () {
    $firstOwner = User::factory()->create();
    $secondOwner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($firstOwner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($secondOwner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($firstOwner)->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('home'))->assertSessionHasNoErrors();

    $this->assertModelMissing($firstOwner);
    $this->assertNotSoftDeleted($team);

    $this->actingAs($secondOwner)->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('password');

    $this->assertAuthenticatedAs($secondOwner);
    $this->assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $secondOwner->id,
        'role' => TeamRole::Owner->value,
    ]);
});
