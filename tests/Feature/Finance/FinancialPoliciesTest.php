<?php

use App\Enums\TeamRole;
use App\Models\AccountBalance;
use App\Models\Currency;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountOwner;
use App\Models\FinancialInstitution;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('financial policies separate household access from holder identity and current team preference', function (?TeamRole $role, bool $read, bool $manage) {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $team = $owner->personalTeam();

    if ($role !== null) {
        $team->members()->attach($actor, ['role' => $role->value]);
    }

    $institution = FinancialInstitution::factory()->create(['team_id' => $team->id]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id, 'financial_institution_id' => $institution->id]);
    $holder = $account->owners()->sole();
    $holder->update(['user_id' => $actor->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $gate = Gate::forUser($actor);

    expect($actor->current_team_id)->not->toBe($team->id);

    foreach ([$institution, $account, $holder, $balance] as $record) {
        expect($gate->allows('view', $record))->toBe($read);
        expect($gate->allows('delete', $record))->toBeFalse();
    }

    expect($gate->allows('viewAny', [FinancialInstitution::class, $team]))->toBe($read);
    expect($gate->allows('viewAny', [FinancialAccount::class, $team]))->toBe($read);
    expect($gate->allows('viewAny', [FinancialAccountOwner::class, $account]))->toBe($read);
    expect($gate->allows('viewAny', [AccountBalance::class, $account]))->toBe($read);
    expect($gate->allows('create', [FinancialInstitution::class, $team]))->toBe($manage);
    expect($gate->allows('create', [FinancialAccount::class, $team]))->toBe($manage);
    expect($gate->allows('create', [FinancialAccountOwner::class, $account]))->toBe($manage);
    expect($gate->allows('create', [AccountBalance::class, $account]))->toBe($manage);
    expect($gate->allows('update', $institution))->toBe($manage);
    expect($gate->allows('update', $account))->toBe($manage);
    expect($gate->allows('update', $holder))->toBe($manage);
    expect($gate->allows('update', $balance))->toBeFalse();
    expect($gate->allows('voidObservation', $balance))->toBe($manage);
    expect($gate->allows('archive', $institution))->toBe($manage);
    expect($gate->allows('archive', $account))->toBe($manage);
})->with([
    'owner' => [TeamRole::Owner, true, true],
    'admin' => [TeamRole::Admin, true, true],
    'member' => [TeamRole::Member, true, false],
    'outside recorded holder' => [null, false, false],
]);

test('membership removal revokes financial access without deleting holder records', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $holder = $account->owners()->sole();
    $holder->update(['user_id' => $member->id]);
    expect(Gate::forUser($member)->allows('view', $account))->toBeTrue();

    $team->memberships()->where('user_id', $member->id)->delete();

    expect(Gate::forUser($member)->allows('view', $account))->toBeFalse();
    $this->assertModelExists($holder);
});

test('archived accounts retain readable history but do not accept new balances', function () {
    $owner = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $owner->current_team_id, 'archived_at' => now()]);
    $gate = Gate::forUser($owner);

    expect($gate->allows('view', $account))->toBeTrue();
    expect($gate->allows('create', [AccountBalance::class, $account]))->toBeFalse();
});

test('deleted teams are not accessible through retained financial records', function () {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);

    $team->delete();

    expect(Gate::forUser($owner)->allows('view', $account))->toBeFalse();
    expect(Gate::forUser($owner)->allows('create', [FinancialAccount::class, $team]))->toBeFalse();
});

test('currencies are readable reference data and cannot be modified through user policies', function () {
    $user = User::factory()->create();
    $currency = Currency::findOrFail('USD');
    $gate = Gate::forUser($user);

    expect($gate->allows('view', $currency))->toBeTrue();
    expect($gate->allows('create', Currency::class))->toBeFalse();
    expect($gate->allows('update', $currency))->toBeFalse();
    expect($gate->allows('delete', $currency))->toBeFalse();
});
