<?php

use App\Enums\FinancialAccountType;
use App\Enums\TeamRole;
use App\Models\AccountBalance;
use App\Models\FinancialAccount;
use App\Models\FinancialInstitution;
use App\Models\Team;
use App\Models\TransactionTransfer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{User, Team, FinancialAccount, FinancialAccount} */
function transferHttpContext(): array
{
    $actor = User::factory()->create();
    $team = $actor->personalTeam();

    return [$actor, $team,
        FinancialAccount::factory()->create(['team_id' => $team->id, 'name' => 'Checking']),
        FinancialAccount::factory()->create(['team_id' => $team->id, 'name' => 'Savings', 'type' => FinancialAccountType::Savings]),
    ];
}

/** @param array<string, mixed> $changes
 * @return array<string, mixed>
 */
function transferHttpInput(FinancialAccount $source, FinancialAccount $destination, array $changes = []): array
{
    return array_replace([
        'source_account_id' => $source->id, 'destination_account_id' => $destination->id,
        'source_amount' => '500.12345678', 'destination_amount' => '500.12345678',
        'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-02',
        'description' => 'Household transfer', 'reason' => 'Manually confirmed',
    ], $changes);
}

test('transfer form exposes only active accounts belonging to the route household', function () {
    [$actor, $team, $source, $destination] = transferHttpContext();
    FinancialAccount::factory()->create(['team_id' => $team->id, 'archived_at' => now()]);
    FinancialAccount::factory()->create();

    $this->actingAs($actor)->get(route('transfers.create', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('transfers/create')
        ->where('team.id', $team->id)->has('accounts', 2)->where('accounts.0.id', $source->id)
        ->where('accounts.1.id', $destination->id)->where('accounts.0.currency_code', 'USD')
        ->where('accounts.0.isLiability', false)->has('today'));
    $this->get(route('transactions.index', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canCreateTransfer', true));
});

test('households without enough accounts receive an empty transfer form without invented balances', function () {
    $actor = User::factory()->create();

    $this->actingAs($actor)->get(route('transfers.create', ['current_team' => $actor->personalTeam()->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('accounts', 0)->missing('balances'));
    $this->get(route('transfers.index', ['current_team' => $actor->personalTeam()->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('transfers/index')->has('transfers.data', 0)->where('transfers.total', 0));
});

test('owners and admins create supported posted transfers through the existing action without changing observations', function (string $scenario, TeamRole $role) {
    [$owner, $team, $source, $destination] = transferHttpContext();
    $actor = User::factory()->create();
    $team->members()->attach($actor, ['role' => $role->value]);
    $destination->update(['type' => match ($scenario) {
        'brokerage' => FinancialAccountType::Brokerage,
        'credit card' => FinancialAccountType::CreditCard,
        default => FinancialAccountType::Savings,
    }, 'currency_code' => $scenario === 'cross currency' ? 'INR' : 'USD']);
    $source->update(['financial_institution_id' => FinancialInstitution::factory()->create(['team_id' => $team->id, 'name' => 'Chase'])->id]);
    $destination->update(['financial_institution_id' => FinancialInstitution::factory()->create(['team_id' => $team->id, 'name' => 'Destination bank'])->id]);
    AccountBalance::factory()->create(['financial_account_id' => $source->id]);
    AccountBalance::factory()->create(['financial_account_id' => $destination->id]);
    $before = AccountBalance::orderBy('id')->get()->toArray();
    $changes = $scenario === 'cross currency' ? [
        'source_amount' => '500', 'destination_amount' => '41500',
        'execution_fx' => [
            'base_currency_code' => 'usd', 'quote_currency_code' => 'inr', 'rate' => '83',
            'source' => 'Bank receipt', 'effective_at' => '2026-10-01T12:00:00.123456+05:30',
        ],
    ] : [];

    $this->actingAs($actor)->post(route('transfers.store', ['current_team' => $team->slug]), transferHttpInput($source, $destination, $changes))
        ->assertSessionHasNoErrors()->assertRedirect();
    $transfer = TransactionTransfer::sole();
    $expectedSource = $scenario === 'cross currency' ? '-500.00000000' : '-500.12345678';
    $expectedDestination = match ($scenario) {
        'credit card' => '-500.12345678',
        'cross currency' => '41500.00000000',
        default => '500.12345678',
    };
    $this->get(route('transfers.show', ['current_team' => $team->slug, 'transaction_transfer' => $transfer->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('transfers/show')
        ->where('transfer.source.amount', $expectedSource)->where('transfer.destination.amount', $expectedDestination)
        ->where('transfer.source.status', 'posted')->where('transfer.destination.status', 'posted')
        ->where('transfer.source.currency_code', 'USD')->where('transfer.destination.currency_code', $scenario === 'cross currency' ? 'INR' : 'USD')
        ->where('transfer.source.account.institution.name', 'Chase')->where('transfer.destination.account.institution.name', 'Destination bank')
        ->where('transfer.destination.account.isLiability', $scenario === 'credit card')
        ->where('transfer.creator.id', $actor->id));
    if ($scenario === 'cross currency') {
        expect($transfer->fx_rate)->toBe('83.000000000000000000');
        expect($transfer->fx_effective_at->format('Y-m-d H:i:s.u'))->toBe('2026-10-01 06:30:00.123456');
    } else {
        expect($transfer->fx_rate)->toBeNull();
    }
    foreach ([$transfer->sourceTransaction, $transfer->destinationTransaction] as $leg) {
        expect($leg->excluded_from_analytics)->toBeTrue();
        expect($leg->revisions()->sole()->actor_id)->toBe($actor->id);
        expect($leg->revisions()->sole()->reason)->toBe('Manually confirmed');
    }
    expect(AccountBalance::orderBy('id')->get()->toArray())->toBe($before);
    $this->assertDatabaseCount('transaction_transfers', 1);
    $this->assertDatabaseCount('account_transactions', 2);
    $this->assertDatabaseCount('transaction_revisions', 2);
})->with(['savings', 'brokerage', 'credit card', 'cross currency'])->with([TeamRole::Owner, TeamRole::Admin]);

test('transfer history paginates whole transfers once and filters either account without double counting legs', function () {
    [$actor, $team] = transferHttpContext();
    $rows = TransactionTransfer::factory()->count(26)->create(['team_id' => $team->id, 'recorded_at' => '2026-10-02 12:00:00.123456']);
    TransactionTransfer::factory()->create();
    $selected = $rows->first();

    $this->actingAs($actor)->get(route('transfers.index', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('transfers.total', 26)
        ->has('transfers.data', 25)->where('transfers.data.0.id', $rows->last()->id));
    $this->get(route('transfers.index', ['current_team' => $team->slug, 'page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('transfers.data', 1)->where('transfers.data.0.id', $selected->id));
    foreach ([$selected->source_account_id, $selected->destination_account_id] as $accountId) {
        $this->get(route('transfers.index', ['current_team' => $team->slug, 'financial_account_id' => $accountId]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('transfers.total', 1)
            ->has('transfers.data', 1)->where('transfers.data.0.id', $selected->id));
    }
});

test('transfer detail preserves high precision decimal strings and optional missing FX provenance', function () {
    [$actor, $team, $source, $destination] = transferHttpContext();
    $this->actingAs($actor)->post(route('transfers.store', ['current_team' => $team->slug]), transferHttpInput($source, $destination, [
        'source_amount' => '9999999999999999.12345678', 'destination_amount' => '9999999999999999.12345678',
    ]))->assertSessionHasNoErrors();

    $this->get(route('transfers.show', ['current_team' => $team->slug, 'transaction_transfer' => TransactionTransfer::sole()->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('transfer.source.amount', '-9999999999999999.12345678')
        ->where('transfer.destination.amount', '9999999999999999.12345678')
        ->where('transfer.execution_fx', null));
});

test('household members read transfer history but cannot create or independently mutate legs', function () {
    [$owner, $team] = transferHttpContext();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $transfer = TransactionTransfer::factory()->create(['team_id' => $team->id]);

    $this->actingAs($member)->get(route('transfers.index', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canCreate', false)->where('transfers.total', 1));
    $this->get(route('transfers.show', ['current_team' => $team->slug, 'transaction_transfer' => $transfer->id]))->assertOk();
    $this->get(route('transfers.create', ['current_team' => $team->slug]))->assertForbidden();
    $this->post(route('transfers.store', ['current_team' => $team->slug]), [])->assertForbidden();
    $this->get(route('transactions.index', ['current_team' => $team->slug]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canCreateTransfer', false));
    $this->assertDatabaseCount('transaction_revisions', 2);
});

test('guests and outsiders cannot access household transfers', function () {
    [$owner, $team] = transferHttpContext();
    $transfer = TransactionTransfer::factory()->create(['team_id' => $team->id]);

    $this->get(route('transfers.index', ['current_team' => $team->slug]))->assertRedirectToRoute('login');
    $this->actingAs(User::factory()->create())->get(route('transfers.index', ['current_team' => $team->slug]))->assertForbidden();
    $this->get(route('transfers.show', ['current_team' => $team->slug, 'transaction_transfer' => $transfer->id]))->assertForbidden();
});

test('cross household group ids accounts and filters are rejected even for members of both teams', function () {
    [$actor, $team, $source, $destination] = transferHttpContext();
    $foreign = TransactionTransfer::factory()->create();
    $foreign->team->members()->attach($actor, ['role' => TeamRole::Admin->value]);

    $this->actingAs($actor)->get(route('transfers.show', ['current_team' => $team->slug, 'transaction_transfer' => $foreign->id]))->assertNotFound();
    $this->postJson(route('transfers.store', ['current_team' => $team->slug]), transferHttpInput($source, $destination, ['destination_account_id' => $foreign->destination_account_id]))
        ->assertUnprocessable()->assertJsonValidationErrors('destination_account_id');
    $this->getJson(route('transfers.index', ['current_team' => $team->slug, 'financial_account_id' => $foreign->source_account_id]))
        ->assertUnprocessable()->assertJsonValidationErrors('financial_account_id');
    $this->assertDatabaseCount('transaction_transfers', 1);
    $this->assertDatabaseCount('account_transactions', 2);
});

test('invalid form values cannot create partial transfers or audits', function (array $changes, string $field) {
    [$actor, $team, $source, $destination] = transferHttpContext();

    $this->actingAs($actor)->postJson(route('transfers.store', ['current_team' => $team->slug]), transferHttpInput($source, $destination, $changes))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('transaction_transfers', 0);
    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_revisions', 0);
})->with([
    [['source_amount' => '-500'], 'source_amount'], [['source_amount' => 500.5], 'source_amount'],
    [['source_amount' => '0'], 'source_amount'], [['source_amount' => '0.123456789'], 'source_amount'],
    [['destination_amount' => '499'], 'destination_amount'], [['destination_amount' => null], 'destination_amount'],
    [['posted_date' => null], 'posted_date'], [['transaction_date' => '2026-02-30'], 'transaction_date'],
    [['team_id' => 900001], 'team_id'], [['source_currency_code' => 'INR'], 'source_currency_code'],
    [['source_transaction_id' => 900001], 'source_transaction_id'],
]);

test('archived and identical accounts are rejected and archived transfer history remains readable', function () {
    [$actor, $team, $source, $destination] = transferHttpContext();
    $this->actingAs($actor)->postJson(route('transfers.store', ['current_team' => $team->slug]), transferHttpInput($source, $source))
        ->assertUnprocessable()->assertJsonValidationErrors('destination_account_id');
    $this->post(route('transfers.store', ['current_team' => $team->slug]), transferHttpInput($source, $destination))->assertSessionHasNoErrors();
    $transfer = TransactionTransfer::sole();
    $source->update(['archived_at' => now()]);
    $this->postJson(route('transfers.store', ['current_team' => $team->slug]), transferHttpInput($source, $destination))
        ->assertUnprocessable()->assertJsonValidationErrors('source_account_id');
    $this->get(route('transfers.index', ['current_team' => $team->slug, 'financial_account_id' => $source->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('transfers.total', 1)
        ->where('transfers.data.0.source.account.archived_at', $source->fresh()->archived_at->toISOString()));
    $this->get(route('transfers.show', ['current_team' => $team->slug, 'transaction_transfer' => $transfer->id]))->assertOk();
    $this->assertDatabaseCount('transaction_revisions', 2);
});

test('cross currency forms require both booked amounts and validate optional execution provenance', function () {
    [$actor, $team, $source, $destination] = transferHttpContext();
    $destination->update(['currency_code' => 'INR']);
    $this->actingAs($actor)->postJson(route('transfers.store', ['current_team' => $team->slug]), transferHttpInput($source, $destination, ['destination_amount' => null]))
        ->assertUnprocessable()->assertJsonValidationErrors('destination_amount');
    $this->postJson(route('transfers.store', ['current_team' => $team->slug]), transferHttpInput($source, $destination, [
        'destination_amount' => '41500', 'execution_fx' => ['base_currency_code' => 'USD', 'quote_currency_code' => 'INR', 'rate' => '0', 'source' => 'Receipt', 'effective_at' => '2026-10-01T12:00:00Z'],
    ]))->assertUnprocessable()->assertJsonValidationErrors('execution_fx.rate');
    $this->assertDatabaseCount('account_transactions', 0);
});

test('transfer routes expose no edits and ordinary transaction routes cannot alter grouped legs', function () {
    [$actor, $team, $source, $destination] = transferHttpContext();
    $this->actingAs($actor)->post(route('transfers.store', ['current_team' => $team->slug]), transferHttpInput($source, $destination))->assertSessionHasNoErrors();
    $transfer = TransactionTransfer::sole();
    $params = ['current_team' => $team->slug, 'transaction_transfer' => $transfer->id];
    $this->put(route('transfers.show', $params), [])->assertMethodNotAllowed();
    $this->delete(route('transfers.show', $params))->assertMethodNotAllowed();
    foreach ([$transfer->source_transaction_id, $transfer->destination_transaction_id] as $legId) {
        $leg = ['current_team' => $team->slug, 'account_transaction' => $legId];
        $this->post(route('transactions.post', $leg), ['expected_revision' => 1, 'posted_date' => '2026-10-03'])->assertForbidden();
        $this->post(route('transactions.cancel', $leg), ['expected_revision' => 1])->assertForbidden();
        $this->patch(route('transactions.classify', $leg), ['expected_revision' => 1, 'kind' => 'income'])->assertForbidden();
    }
    $this->assertDatabaseCount('transaction_transfers', 1);
    $this->assertDatabaseCount('transaction_revisions', 2);
});
