<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use App\Models\AccountBalance;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('fallback team exclusion is optional and preserves the existing alphabetical membership selection', function () {
    $user = User::factory()->create();
    $personal = $user->personalTeam();
    $personal->update(['name' => 'Z personal']);
    $shared = Team::factory()->create(['name' => 'A family']);
    $shared->members()->attach($user, ['role' => TeamRole::Member->value]);

    expect($user->fallbackTeam())->id->toBe($shared->id);
    expect($user->fallbackTeam(null))->id->toBe($shared->id);
    expect($user->fallbackTeam($shared))->id->toBe($personal->id);
});

test('personal and fallback team lookups remain legitimately nullable and never borrow the global users team', function () {
    $user = User::factory()->create();
    $personal = $user->personalTeam();
    $personal->delete();
    $shared = Team::factory()->create();
    $shared->members()->attach($user, ['role' => TeamRole::Member->value]);
    $this->actingAs(User::factory()->create());

    expect($user->personalTeam())->toBeNull();
    expect($user->fallbackTeam($shared))->toBeNull();
    expect($user->fallbackTeam())->id->toBe($shared->id);
});

test('member removal rolls back when a required personal team is missing instead of assigning an unrelated team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $member->personalTeam()->delete();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $before = $member->fresh()->getAttributes();

    $this->actingAs($owner)->deleteJson(route('teams.members.destroy', ['team' => $team->slug, 'user' => $member->id]))
        ->assertUnprocessable()->assertJsonPath('errors.member.0', 'The member has no personal team to switch to.');

    expect($member->fresh()->getAttributes())->toBe($before);
    expect($member->fresh()->teamRole($team))->toBe(TeamRole::Member);
    expect($owner->fresh()->ownsTeam($team))->toBeTrue();
});

test('team deletion rolls back affected users memberships invitations and financial history when a required personal team is missing', function () {
    $owner = User::factory()->create();
    $firstMember = User::factory()->create();
    $missingMember = User::factory()->create();
    $missingMember->personalTeam()->delete();
    $team = Team::factory()->create();
    foreach ([$owner, $firstMember, $missingMember] as $user) {
        $team->members()->attach($user, ['role' => $user->is($owner) ? TeamRole::Owner->value : TeamRole::Member->value]);
        $user->switchTeam($team);
    }
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'invited_by' => $owner->id]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $before = [];
    foreach (['users', 'teams', 'team_members', 'team_invitations', 'financial_accounts', 'financial_account_owners', 'account_balances'] as $table) {
        $before[$table] = hash('sha256', DB::table($table)->orderBy('id')->get()->toJson());
    }

    $this->actingAs($owner)->deleteJson(route('teams.destroy', $team), ['name' => $team->name])
        ->assertUnprocessable()->assertJsonPath('errors.name.0', 'A user currently using this team has no personal team to switch to.');

    foreach ($before as $table => $hash) {
        expect(hash('sha256', DB::table($table)->orderBy('id')->get()->toJson()))->toBe($hash);
    }
    $this->assertModelExists($invitation);
    $this->assertModelExists($balance);
    expect($team->fresh()->trashed())->toBeFalse();
});

test('a missing personal team does not prevent removal when no personal team switch is needed', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $member->personalTeam()->delete();
    $other = Team::factory()->create();
    $other->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($other);
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner)->delete(route('teams.members.destroy', ['team' => $team->slug, 'user' => $member->id]))->assertRedirect();

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
    expect($member->fresh()->current_team_id)->toBe($other->id);
});
