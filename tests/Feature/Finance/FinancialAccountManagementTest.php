<?php

use App\Enums\TeamRole;
use App\Models\AccountBalance;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountOwner;
use App\Models\FinancialInstitution;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function manualAccountPayload(?FinancialAccount $account = null): array
{
    return [
        'name' => 'Family account',
        'type' => 'savings',
        'currency_code' => 'USD',
        'financial_institution_id' => null,
        'owners' => $account?->owners()->get()->map(fn ($owner) => $owner->only(['id', 'owner_name', 'user_id']))->all()
            ?? [['owner_name' => 'Family holder', 'user_id' => null]],
    ];
}

test('owners and admins create accounts and custom institutions in the route household', function (TeamRole $role) {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($actor, ['role' => $role->value]);
    $input = manualAccountPayload();
    $input['institution'] = ['name' => 'Local credit union', 'country_code' => 'US'];
    $input['owners'][] = ['owner_name' => $owner->name, 'user_id' => $owner->id];

    $this->actingAs($actor)->post(route('accounts.store', ['current_team' => $team->slug]), $input)
        ->assertSessionHasNoErrors()->assertRedirect();

    $account = FinancialAccount::sole();
    expect($account->team_id)->toBe($team->id);
    expect($account->created_by)->toBe($actor->id);
    expect($account->owners)->toHaveCount(2);
    $this->assertDatabaseHas('financial_institutions', ['id' => $account->financial_institution_id, 'team_id' => $team->id, 'name' => 'Local credit union']);
})->with([TeamRole::Owner, TeamRole::Admin]);

test('members list and view only records from the route household and can filter type and status', function () {
    $member = User::factory()->create();
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $checking = FinancialAccount::factory()->create(['team_id' => $team->id, 'name' => 'Current account']);
    FinancialAccount::factory()->create(['team_id' => $team->id, 'type' => 'loan']);
    $archived = FinancialAccount::factory()->create(['team_id' => $team->id, 'archived_at' => now()]);
    FinancialAccount::factory()->create();

    $this->actingAs($member)->get(route('accounts.index', ['current_team' => $team->slug, 'type' => 'checking']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('accounts/index')
        ->where('team.id', $team->id)->where('canManage', false)
        ->has('accounts.data', 1)->where('accounts.data.0.id', $checking->id)
        ->where('accounts.data.0.currency_code', 'USD')->has('accounts.data.0.owners', 1));

    $this->get(route('accounts.index', ['current_team' => $team->slug, 'status' => 'archived']))
        ->assertInertia(fn (Assert $page) => $page->has('accounts.data', 1)->where('accounts.data.0.id', $archived->id));

    $this->get(route('accounts.show', ['current_team' => $team->slug, 'financial_account' => $checking->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('accounts/show')->where('account.id', $checking->id)->where('canManage', false));
});

test('create and edit forms offer only household institutions and members', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $institution = FinancialInstitution::factory()->create(['team_id' => $team->id]);
    FinancialInstitution::factory()->create();
    FinancialInstitution::factory()->create(['team_id' => $team->id, 'archived_at' => now()]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);

    $this->actingAs($actor)->get(route('accounts.create', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('accounts/form')
        ->where('account', null)->has('currencies', 2)->has('members', 1)
        ->where('members.0.id', $actor->id)->has('institutions', 1)->where('institutions.0.id', $institution->id));

    $this->get(route('accounts.edit', ['current_team' => $team->slug, 'financial_account' => $account->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('accounts/form')->where('account.id', $account->id)->where('hasHistory', false));
});

test('account edits update metadata and retained holders and add holders atomically', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $holder = $account->owners()->sole();
    $input = manualAccountPayload($account);
    $input['name'] = 'Updated family account';
    $input['type'] = 'loan';
    $input['currency_code'] = 'INR';
    $input['institution'] = ['name' => 'Custom bank', 'country_code' => 'IN'];
    $input['owners'][0]['owner_name'] = 'Corrected holder name';
    $input['owners'][0]['user_id'] = $actor->id;
    $input['owners'][] = ['owner_name' => 'Joint holder'];

    $this->actingAs($actor)->put(route('accounts.update', ['current_team' => $team->slug, 'financial_account' => $account->id]), $input)
        ->assertSessionHasNoErrors()->assertRedirectToRoute('accounts.show', ['current_team' => $team->slug, 'financial_account' => $account->id]);

    $this->assertDatabaseHas('financial_accounts', ['id' => $account->id, 'name' => 'Updated family account', 'type' => 'loan', 'currency_code' => 'INR']);
    $this->assertDatabaseHas('financial_account_owners', ['id' => $holder->id, 'owner_name' => 'Corrected holder name', 'user_id' => $actor->id]);
    expect($account->fresh()->owners)->toHaveCount(2);
    expect($account->fresh()->institution->name)->toBe('Custom bank');
});

test('archive requires confirmation and preserves all financial history and holder records', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $holderId = $account->owners()->sole()->id;
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $parameters = ['current_team' => $team->slug, 'financial_account' => $account->id];

    $this->actingAs($actor)->post(route('accounts.archive', $parameters))->assertSessionHasErrors('confirmation');
    expect($account->fresh()->archived_at)->toBeNull();

    $this->post(route('accounts.archive', $parameters), ['confirmation' => true])
        ->assertSessionHasNoErrors()->assertRedirectToRoute('accounts.index', ['current_team' => $team->slug]);
    expect($account->fresh()->archived_at)->not->toBeNull();
    $this->assertDatabaseHas('account_balances', ['id' => $balance->id, 'financial_account_id' => $account->id]);
    $this->assertDatabaseHas('financial_account_owners', ['id' => $holderId, 'financial_account_id' => $account->id]);

    $this->get(route('accounts.show', $parameters))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    $this->put(route('accounts.update', $parameters), manualAccountPayload($account))->assertForbidden();
});

test('linked members can be reassigned between retained holders without unique key collisions', function () {
    $actor = User::factory()->create();
    $jointHolder = User::factory()->create();
    $team = $actor->personalTeam();
    $team->members()->attach($jointHolder, ['role' => TeamRole::Member->value]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $firstHolder = $account->owners()->sole();
    $firstHolder->update(['user_id' => $actor->id]);
    $secondHolder = FinancialAccountOwner::factory()->create([
        'team_id' => $team->id,
        'financial_account_id' => $account->id,
        'user_id' => $jointHolder->id,
    ]);
    $input = manualAccountPayload($account);
    $input['owners'] = [
        ['id' => $firstHolder->id, 'owner_name' => 'First corrected holder', 'user_id' => $jointHolder->id],
        ['id' => $secondHolder->id, 'owner_name' => 'Second corrected holder', 'user_id' => $actor->id],
    ];

    $this->actingAs($actor)->put(route('accounts.update', ['current_team' => $team->slug, 'financial_account' => $account->id]), $input)
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($firstHolder->fresh()->user_id)->toBe($jointHolder->id);
    expect($secondHolder->fresh()->user_id)->toBe($actor->id);
    expect($account->owners()->count())->toBe(2);
});

test('ordinary members cannot use account management endpoints', function (string $routeName, string $method) {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $team = $owner->personalTeam();
    $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $parameters = ['current_team' => $team->slug, 'financial_account' => $account->id];

    $this->actingAs($actor)->call($method, route($routeName, $parameters), manualAccountPayload($account) + ['confirmation' => true])->assertForbidden();
    expect($account->fresh()->archived_at)->toBeNull();
})->with([
    'create form' => ['accounts.create', 'GET'],
    'create' => ['accounts.store', 'POST'],
    'edit form' => ['accounts.edit', 'GET'],
    'update' => ['accounts.update', 'PUT'],
    'archive' => ['accounts.archive', 'POST'],
]);

test('unauthenticated account requests require login', function (string $method, string $routeName) {
    $team = Team::factory()->create();

    $this->call($method, route($routeName, ['current_team' => $team->slug]), manualAccountPayload())->assertRedirectToRoute('login');
})->with([['GET', 'accounts.index'], ['POST', 'accounts.store']]);

test('outsiders cannot list or create in another household', function (string $method, string $routeName) {
    $actor = User::factory()->create();
    $team = User::factory()->create()->personalTeam();

    $this->actingAs($actor)->call($method, route($routeName, ['current_team' => $team->slug]), manualAccountPayload())->assertForbidden();
    $this->assertDatabaseCount('financial_accounts', 0);
})->with([['GET', 'accounts.index'], ['POST', 'accounts.store']]);

test('nested account binding rejects other household records even when the actor belongs to both', function (string $routeName, string $method) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $otherTeam = User::factory()->create()->personalTeam();
    $otherTeam->members()->attach($actor, ['role' => TeamRole::Admin->value]);
    $account = FinancialAccount::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($actor)->call($method, route($routeName, ['current_team' => $team->slug, 'financial_account' => $account->id]), manualAccountPayload($account) + ['confirmation' => true])->assertNotFound();
    expect($account->fresh()->archived_at)->toBeNull();
})->with([
    'view' => ['accounts.show', 'GET'],
    'edit' => ['accounts.edit', 'GET'],
    'update' => ['accounts.update', 'PUT'],
    'archive' => ['accounts.archive', 'POST'],
]);

test('existing institution selection must be active and belong to the route team', function (string $case) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $institution = FinancialInstitution::factory()->create([
        'team_id' => $case === 'other household' ? User::factory()->create()->current_team_id : $team->id,
        'archived_at' => $case === 'archived' ? now() : null,
    ]);
    $input = manualAccountPayload();
    $input['financial_institution_id'] = $institution->id;

    $response = $this->actingAs($actor)->post(route('accounts.store', ['current_team' => $team->slug]), $input);
    $response->assertSessionHasErrors('financial_institution_id');
    $this->assertDatabaseCount('financial_accounts', 0);
})->with(['other household', 'archived']);

test('existing institutions may be selected and an existing archived institution may be retained', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $institution = FinancialInstitution::factory()->create(['team_id' => $team->id]);
    $input = manualAccountPayload();
    $input['financial_institution_id'] = $institution->id;

    $this->actingAs($actor)->post(route('accounts.store', ['current_team' => $team->slug]), $input)->assertSessionHasNoErrors();
    $account = FinancialAccount::sole();
    expect($account->financial_institution_id)->toBe($institution->id);
    $institution->update(['archived_at' => now()]);
    $input = manualAccountPayload($account);
    $input['financial_institution_id'] = $institution->id;

    $this->put(route('accounts.update', ['current_team' => $team->slug, 'financial_account' => $account->id]), $input)->assertSessionHasNoErrors();
    expect($account->fresh()->financial_institution_id)->toBe($institution->id);
});

test('invalid account holders and controlled fields return validation errors without partial records', function (string $case, string $field) {
    $actor = User::factory()->create();
    $outsider = User::factory()->create();
    $team = $actor->personalTeam();
    $input = manualAccountPayload();
    $input['institution'] = ['name' => 'Must not persist'];
    $input = match ($case) {
        'no holders' => array_replace($input, ['owners' => []]),
        'nameless holder' => array_replace($input, ['owners' => [['owner_name' => '']]]),
        'outside linked user' => array_replace($input, ['owners' => [['owner_name' => 'Outsider', 'user_id' => $outsider->id]]]),
        'missing linked user' => array_replace($input, ['owners' => [['owner_name' => 'Missing user', 'user_id' => 999999]]]),
        'duplicate linked user' => array_replace($input, ['owners' => [['owner_name' => 'First', 'user_id' => $actor->id], ['owner_name' => 'Second', 'user_id' => $actor->id]]]),
        'spoofed team' => array_replace($input, ['team_id' => $outsider->current_team_id]),
        'nested institution team' => array_replace($input, ['institution' => ['name' => 'Spoofed', 'team_id' => $outsider->current_team_id]]),
        'nested holder team' => array_replace($input, ['owners' => [['owner_name' => 'Spoofed', 'team_id' => $outsider->current_team_id]]]),
        'owner id during creation' => array_replace($input, ['owners' => [['id' => 123, 'owner_name' => 'Spoofed']]]),
        'both institution inputs' => array_replace($input, ['financial_institution_id' => FinancialInstitution::factory()->create(['team_id' => $team->id])->id]),
        'invalid country' => array_replace($input, ['institution' => ['name' => 'Bank', 'country_code' => 'USA']]),
    };

    $this->actingAs($actor)->post(route('accounts.store', ['current_team' => $team->slug]), $input)->assertSessionHasErrors($field);
    $this->assertDatabaseCount('financial_accounts', 0);
    $this->assertDatabaseMissing('financial_institutions', ['name' => 'Must not persist']);
})->with([
    ['no holders', 'owners'], ['nameless holder', 'owners.0.owner_name'],
    ['outside linked user', 'owners.0.user_id'], ['duplicate linked user', 'owners.0.user_id'],
    ['missing linked user', 'owners.0.user_id'],
    ['spoofed team', 'team_id'], ['nested institution team', 'institution'],
    ['nested holder team', 'owners.0'], ['owner id during creation', 'owners.0.id'],
    ['both institution inputs', 'institution'], ['invalid country', 'institution.country_code'],
]);

test('account edits reject holder ids from other accounts or teams and retain existing holder records', function (string $case) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $other = FinancialAccount::factory()->create(['team_id' => $case === 'other account' ? $team->id : User::factory()->create()->current_team_id]);
    $input = manualAccountPayload($account);
    $input['owners'] = [['id' => $other->owners()->sole()->id, 'owner_name' => 'Attempted replacement']];
    $input['institution'] = ['name' => 'Must not persist'];

    $this->actingAs($actor)->put(route('accounts.update', ['current_team' => $team->slug, 'financial_account' => $account->id]), $input)
        ->assertSessionHasErrors('owners.0.id');

    $this->assertDatabaseCount('financial_account_owners', 2);
    $this->assertDatabaseMissing('financial_institutions', ['name' => 'Must not persist']);
})->with(['other account', 'other team']);

test('account edits cannot silently drop previously recorded holders', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $input = manualAccountPayload();
    $input['name'] = 'Rejected update';

    $this->actingAs($actor)->put(route('accounts.update', ['current_team' => $team->slug, 'financial_account' => $account->id]), $input)
        ->assertSessionHasErrors(['owners' => 'Keep all recorded holders. You may edit their details or add holders.']);

    expect($account->fresh()->name)->not->toBe('Rejected update');
    expect($account->owners()->count())->toBe(1);
});

test('a departed member link remains valid for its recorded holder but cannot be assigned to another', function () {
    $actor = User::factory()->create();
    $formerMember = User::factory()->create();
    $team = $actor->personalTeam();
    $team->members()->attach($formerMember, ['role' => TeamRole::Member->value]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $account->owners()->sole()->update(['user_id' => $formerMember->id]);
    $team->members()->detach($formerMember);
    $input = manualAccountPayload($account);
    $parameters = ['current_team' => $team->slug, 'financial_account' => $account->id];

    $this->actingAs($actor)->put(route('accounts.update', $parameters), $input)->assertSessionHasNoErrors();
    expect($account->owners()->sole()->user_id)->toBe($formerMember->id);
    $input['owners'][0]['user_id'] = null;
    $input['owners'][] = ['owner_name' => 'New holder', 'user_id' => $formerMember->id];

    $this->put(route('accounts.update', $parameters), $input)->assertSessionHasErrors('owners.1.user_id');
    expect($account->owners()->sole()->user_id)->toBe($formerMember->id);
});

test('currency and classification stay immutable after any recorded balance including voided observations', function (string $field, string $value, bool $voided) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    AccountBalance::factory()->create(['financial_account_id' => $account->id, 'voided_at' => $voided ? now() : null]);
    $input = manualAccountPayload($account);
    $input[$field] = $value;

    $this->actingAs($actor)->put(route('accounts.update', ['current_team' => $team->slug, 'financial_account' => $account->id]), $input)
        ->assertSessionHasErrors([$field => $field === 'type' ? 'Asset/liability classification cannot change after balance history exists.' : 'Currency cannot change after balance history exists.']);

    $this->assertDatabaseHas('financial_accounts', ['id' => $account->id, 'type' => 'checking', 'currency_code' => 'USD']);
})->with([['currency_code', 'INR', false], ['type', 'loan', false], ['currency_code', 'INR', true], ['type', 'loan', true]]);

test('types may change within their classification after balance history exists', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    AccountBalance::factory()->create(['financial_account_id' => $account->id]);

    $this->actingAs($actor)->put(route('accounts.update', ['current_team' => $team->slug, 'financial_account' => $account->id]), manualAccountPayload($account))
        ->assertSessionHasNoErrors();
    $this->assertDatabaseHas('financial_accounts', ['id' => $account->id, 'type' => 'savings', 'currency_code' => 'USD']);
});

test('a failed holder write rolls back account changes and inline institution creation', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $input = manualAccountPayload($account);
    $input['name'] = 'Must roll back';
    $input['institution'] = ['name' => 'Must roll back bank'];
    $input['owners'][] = ['owner_name' => 'Rejected new holder'];
    FinancialAccountOwner::creating(function (FinancialAccountOwner $holder): void {
        if ($holder->owner_name === 'Rejected new holder') {
            throw new DomainException('Holder write failed.');
        }
    });
    Exceptions::fake();

    $this->actingAs($actor)->put(route('accounts.update', ['current_team' => $team->slug, 'financial_account' => $account->id]), $input)
        ->assertInternalServerError();
    Exceptions::assertReported(fn (DomainException $exception) => $exception->getMessage() === 'Holder write failed.');

    $this->assertDatabaseMissing('financial_accounts', ['id' => $account->id, 'name' => 'Must roll back']);
    $this->assertDatabaseMissing('financial_institutions', ['name' => 'Must roll back bank']);
    expect($account->owners()->count())->toBe(1);
});

test('a retained holder disappearing after validation rolls back every account edit and permits a clean retry', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $first = $account->owners()->sole();
    $first->update(['user_id' => $actor->id]);
    $second = FinancialAccountOwner::factory()->create(['financial_account_id' => $account->id, 'team_id' => $team->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '100.12345678']);
    $beforeAccount = $account->fresh()->getAttributes();
    $beforeOwners = DB::table('financial_account_owners')->orderBy('id')->get()->toArray();
    $beforeBalance = $balance->fresh()->getAttributes();
    $input = manualAccountPayload($account);
    $input['name'] = 'Accepted after retry';
    $input['type'] = $account->type->value;
    $input['institution'] = ['name' => 'Retry bank'];
    $input['owners'] = [
        ['id' => $first->id, 'owner_name' => 'Corrected first holder', 'user_id' => $actor->id],
        ['id' => $second->id, 'owner_name' => $second->owner_name, 'user_id' => null],
    ];
    $parameters = ['current_team' => $team->slug, 'financial_account' => $account->id];
    $event = 'eloquent.updated: '.FinancialAccount::class;
    Event::listen($event, function (FinancialAccount $updated) use ($account, $second): void {
        if ($updated->id === $account->id) {
            DB::table('financial_account_owners')->where('id', $second->id)->delete();
        }
    });

    try {
        $this->actingAs($actor)->put(route('accounts.update', $parameters), $input)
            ->assertSessionHasErrors('owners.1.id');
    } finally {
        Event::forget($event);
    }

    expect($account->fresh()->getAttributes())->toBe($beforeAccount);
    expect(DB::table('financial_account_owners')->orderBy('id')->get()->toArray())->toEqual($beforeOwners);
    expect($balance->fresh()->getAttributes())->toBe($beforeBalance);
    $this->assertDatabaseMissing('financial_institutions', ['name' => 'Retry bank']);

    $this->put(route('accounts.update', $parameters), $input)->assertSessionHasNoErrors()->assertRedirect();

    expect($account->fresh()->name)->toBe('Accepted after retry');
    expect($account->owners()->orderBy('id')->pluck('id')->all())->toBe([$first->id, $second->id]);
    expect($first->fresh()->owner_name)->toBe('Corrected first holder');
    expect($first->fresh()->user_id)->toBe($actor->id);
    expect($balance->fresh()->getAttributes())->toBe($beforeBalance);
});
