<?php

use App\Actions\Finance\CreateFinancialAccount;
use App\Enums\TeamRole;
use App\Models\FinancialAccountOwner;
use App\Models\FinancialInstitution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** @return array{name: string, type: string, currency_code: string, owners: list<array{owner_name: string, user_id: int}>} */
function financialAccountInput(User $holder): array
{
    return [
        'name' => 'Household savings',
        'type' => 'savings',
        'currency_code' => 'INR',
        'owners' => [['owner_name' => $holder->name, 'user_id' => $holder->id]],
    ];
}

test('authorized owners and admins create accounts with recorded joint holders in an explicit team', function (TeamRole $role) {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($actor, ['role' => $role->value]);
    $institution = FinancialInstitution::factory()->create(['team_id' => $team->id]);
    $input = financialAccountInput($owner);
    $input['financial_institution_id'] = $institution->id;
    $input['owners'][] = ['owner_name' => 'Family holder without a login'];

    $account = app(CreateFinancialAccount::class)->handle($actor, $team, $input);

    expect($actor->fresh()->current_team_id)->not->toBe($team->id);
    expect($account->team_id)->toBe($team->id);
    expect($account->currency->code)->toBe('INR');
    expect($account->institution->is($institution))->toBeTrue();
    expect($account->owners)->toHaveCount(2);
    $this->assertDatabaseHas('financial_account_owners', ['financial_account_id' => $account->id, 'owner_name' => 'Family holder without a login', 'user_id' => null]);
    $this->assertDatabaseHas('financial_accounts', ['id' => $account->id, 'created_by' => $actor->id]);
})->with([TeamRole::Owner, TeamRole::Admin]);

test('members and outsiders cannot create financial accounts', function (?TeamRole $role) {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $team = $owner->personalTeam();

    if ($role !== null) {
        $team->members()->attach($actor, ['role' => $role->value]);
    }

    expect(fn () => app(CreateFinancialAccount::class)->handle($actor, $team, financialAccountInput($owner)))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseCount('financial_accounts', 0);
    $this->assertDatabaseCount('financial_account_owners', 0);
})->with([TeamRole::Member, null]);

test('account creation rejects invalid holder and controlled attributes without partial records', function (string $case) {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $team = $owner->personalTeam();
    $input = financialAccountInput($owner);
    $input = match ($case) {
        'no holders' => array_replace($input, ['owners' => []]),
        'empty name' => array_replace($input, ['owners' => [['owner_name' => '']]]),
        'outside holder link' => array_replace($input, ['owners' => [['owner_name' => 'Outside holder', 'user_id' => $outsider->id]]]),
        'duplicate linked holder' => array_replace($input, ['owners' => [$input['owners'][0], $input['owners'][0]]]),
        'spoofed team' => array_replace($input, ['team_id' => $outsider->current_team_id]),
        'spoofed actor' => array_replace($input, ['created_by' => $outsider->id]),
        'invalid type' => array_replace($input, ['type' => 'unsupported']),
        'invalid currency' => array_replace($input, ['currency_code' => 'ZZZ']),
        'holder extra attribute' => array_replace($input, ['owners' => [['owner_name' => 'Holder', 'team_id' => $outsider->current_team_id]]]),
    };

    expect(fn () => app(CreateFinancialAccount::class)->handle($owner, $team, $input))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('financial_accounts', 0);
    $this->assertDatabaseCount('financial_account_owners', 0);
})->with(['no holders', 'empty name', 'outside holder link', 'duplicate linked holder', 'spoofed team', 'spoofed actor', 'invalid type', 'invalid currency', 'holder extra attribute']);

test('account creation rejects institutions from another team or archived institutions', function (bool $archived) {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $institution = FinancialInstitution::factory()->create([
        'team_id' => $archived ? $team->id : User::factory()->create()->current_team_id,
        'archived_at' => $archived ? now() : null,
    ]);
    $input = financialAccountInput($owner);
    $input['financial_institution_id'] = $institution->id;

    expect(fn () => app(CreateFinancialAccount::class)->handle($owner, $team, $input))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('financial_accounts', 0);
})->with([false, true]);

test('a holder persistence failure rolls back the account and all preceding holder writes', function () {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $input = financialAccountInput($owner);
    $input['owners'][] = ['owner_name' => 'Rejected holder'];
    FinancialAccountOwner::creating(function (FinancialAccountOwner $holder): void {
        if ($holder->owner_name === 'Rejected holder') {
            throw new DomainException('Holder persistence failed.');
        }
    });

    expect(fn () => app(CreateFinancialAccount::class)->handle($owner, $team, $input))
        ->toThrow(DomainException::class, 'Holder persistence failed.');

    $this->assertDatabaseCount('financial_accounts', 0);
    $this->assertDatabaseCount('financial_account_owners', 0);
});
