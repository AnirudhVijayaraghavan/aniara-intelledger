<?php

use App\Actions\Finance\CreateTransfer;
use App\Actions\Finance\ReverseTransfer;
use App\Enums\FinancialAccountType;
use App\Enums\TeamRole;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionRevision;
use App\Models\TransactionTransfer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{User, Team, FinancialAccount, FinancialAccount, TransactionTransfer} */
function transferReversalHttpContext(string $scenario = 'savings'): array
{
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $source = FinancialAccount::factory()->create(['team_id' => $team->id, 'name' => 'Household checking']);
    $destination = FinancialAccount::factory()->create([
        'team_id' => $team->id, 'name' => 'Destination account',
        'type' => match ($scenario) {
            'credit card' => FinancialAccountType::CreditCard,
            'loan' => FinancialAccountType::Loan,
            default => FinancialAccountType::Savings,
        }, 'currency_code' => $scenario === 'cross currency' ? 'INR' : 'USD',
    ]);
    $transfer = app(CreateTransfer::class)->handle($actor, $team, [
        'source_account_id' => $source->id, 'destination_account_id' => $destination->id,
        'source_amount' => '500.12345678', 'destination_amount' => $scenario === 'cross currency' ? '41510.24691357' : '500.12345678',
        'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-02',
        'execution_fx' => $scenario === 'cross currency' ? [
            'base_currency_code' => 'USD', 'quote_currency_code' => 'INR', 'rate' => '83.123456789012345678',
            'source' => 'Original bank execution', 'effective_at' => '2026-10-01T12:00:00.123456Z',
        ] : null,
    ]);

    return [$actor, $team, $source, $destination, $transfer];
}

/** @return array<string, mixed> */
function transferReversalHttpInput(array $overrides = []): array
{
    return array_replace(['reason' => 'Recorded in error', 'posted_date' => '2026-10-03',
        'expected_source_revision' => 1, 'expected_destination_revision' => 1], $overrides);
}

test('owners and admins confirm exact reversal previews and navigate original and reversal details', function (string $scenario, TeamRole $role) {
    [$owner, $team, $source, $destination, $original] = transferReversalHttpContext($scenario);
    $actor = User::factory()->create();
    $team->members()->attach($actor, ['role' => $role->value]);
    AccountBalance::factory()->create(['financial_account_id' => $source->id]);
    AccountBalance::factory()->create(['financial_account_id' => $destination->id]);
    $balances = AccountBalance::orderBy('id')->get()->toArray();
    $groupBefore = $original->fresh()->getAttributes();
    $oldLegs = AccountTransaction::orderBy('id')->get()->toArray();
    $oldAudits = TransactionRevision::orderBy('id')->get()->toArray();
    $params = ['current_team' => $team->slug, 'transaction_transfer' => $original->id];
    $expectedDestination = match ($scenario) {
        'credit card', 'loan' => '500.12345678',
        'cross currency' => '-41510.24691357',
        default => '-500.12345678',
    };
    $this->actingAs($actor)->get(route('transfers.show', $params))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('transfers/show')->where('canReverse', true)
        ->where('reversalPreview.source_amount', '500.12345678')->where('reversalPreview.destination_amount', $expectedDestination)
        ->where('reversalPreview.minimum_posted_date', '2026-10-02')
        ->where('transfer.source.expected_revision', 1)->where('transfer.destination.expected_revision', 1)
        ->where('transfer.source.account.id', $source->id)->where('transfer.destination.account.id', $destination->id)
        ->where('transfer.destination.currency_code', $destination->currency_code)
        ->where('transfer.reversal', null)->where('transfer.reverses_transfer_id', null)->has('today'));
    $this->assertDatabaseCount('transaction_transfers', 1);
    $this->assertDatabaseCount('transaction_revisions', 2);

    $this->post(route('transfers.reverse', $params), transferReversalHttpInput())->assertSessionHasNoErrors()->assertRedirect();
    $reversal = $original->fresh()->reversal;
    expect($reversal)->not->toBeNull();
    expect($reversal->created_by)->toBe($actor->id);
    expect($reversal->sourceTransaction->amount)->toBe($expectedDestination);
    expect($reversal->destinationTransaction->amount)->toBe('500.12345678');
    foreach ([$reversal->sourceTransaction, $reversal->destinationTransaction] as $leg) {
        $revision = $leg->revisions()->sole();
        expect($revision->revision_number)->toBe(1);
        expect($revision->actor_id)->toBe($actor->id);
        expect($revision->reason)->toBe('Recorded in error');
        expect($revision->snapshot)->toBe($leg->fresh()->canonicalSnapshot());
    }
    expect($original->fresh()->getAttributes())->toBe($groupBefore);
    expect(AccountTransaction::whereIn('id', [$original->source_transaction_id, $original->destination_transaction_id])->orderBy('id')->get()->toArray())->toBe($oldLegs);
    expect(TransactionRevision::whereIn('account_transaction_id', [$original->source_transaction_id, $original->destination_transaction_id])->orderBy('id')->get()->toArray())->toBe($oldAudits);
    expect(AccountBalance::orderBy('id')->get()->toArray())->toBe($balances);
    $this->get(route('transfers.show', $params))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('canReverse', false)->where('reversalPreview', null)->where('transfer.reversal.id', $reversal->id));
    $this->get(route('transfers.show', ['current_team' => $team->slug, 'transaction_transfer' => $reversal->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canReverse', false)->where('reversalPreview', null)
        ->where('transfer.reverses_transfer_id', $original->id)->where('reversalReason', 'Recorded in error')
        ->where('transfer.execution_fx', null)->where('transfer.original_execution_fx', $scenario === 'cross currency'
            ? fn ($fx) => $fx['rate'] === '83.123456789012345678' && $fx['source'] === 'Original bank execution'
            : null));
    $this->assertDatabaseCount('transaction_transfers', 2);
    $this->assertDatabaseCount('account_transactions', 4);
    $this->assertDatabaseCount('transaction_revisions', 4);
})->with(['savings', 'credit card', 'loan', 'cross currency'])->with([TeamRole::Owner, TeamRole::Admin]);

test('history counts original transfers once and links their corrections through either account filter', function (bool $sourceSide) {
    [$actor, $team, $source, $destination, $original] = transferReversalHttpContext();
    $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, transferReversalHttpInput());
    $this->actingAs($actor)->get(route('transfers.index', ['current_team' => $team->slug, 'financial_account_id' => ($sourceSide ? $source : $destination)->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('transfers.data', 1)->where('transfers.total', 1)
        ->where('transfers.data.0.id', $original->id)->where('transfers.data.0.reversal.id', $reversal->id)
        ->where('transfers.data.0.reverses_transfer_id', null));
})->with([true, false]);

test('members can inspect both histories without reversal permission and outsiders cannot mutate them', function (bool $member) {
    [$owner, $team, $source, $destination, $original] = transferReversalHttpContext();
    $actor = User::factory()->create();
    if ($member) {
        $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    }
    $params = ['current_team' => $team->slug, 'transaction_transfer' => $original->id];
    $this->actingAs($actor)->postJson(route('transfers.reverse', $params), transferReversalHttpInput())->assertForbidden();
    if ($member) {
        $this->get(route('transfers.show', $params))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canReverse', false)->where('reversalPreview', null));
        $reversal = app(ReverseTransfer::class)->handle($owner, $team, $original, transferReversalHttpInput());
        $this->get(route('transfers.show', ['current_team' => $team->slug, 'transaction_transfer' => $reversal->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canReverse', false)->where('transfer.reverses_transfer_id', $original->id));
    } else {
        $this->get(route('transfers.show', $params))->assertForbidden();
        $this->assertDatabaseCount('transaction_revisions', 2);
    }
})->with([true, false]);

test('guests are redirected from transfer reversal to login', function () {
    [$owner, $team, $source, $destination, $original] = transferReversalHttpContext();
    $this->post(route('transfers.reverse', ['current_team' => $team->slug, 'transaction_transfer' => $original->id]), transferReversalHttpInput())->assertRedirect(route('login'));
    $this->assertDatabaseCount('transaction_transfers', 1);
});

test('explicit route teams reject foreign transfer details and mutations even with membership in both households', function () {
    [$actor, $team, $source, $destination, $original] = transferReversalHttpContext();
    [$other, $otherTeam, $otherSource, $otherDestination, $foreign] = transferReversalHttpContext();
    $otherTeam->members()->attach($actor, ['role' => TeamRole::Owner->value]);
    $actor->switchTeam($otherTeam);
    $params = ['current_team' => $team->slug, 'transaction_transfer' => $foreign->id];
    $this->actingAs($actor)->get(route('transfers.show', $params))->assertNotFound();
    $this->postJson(route('transfers.reverse', $params), transferReversalHttpInput())->assertNotFound();
    $this->post(route('transfers.reverse', ['current_team' => $team->slug, 'transaction_transfer' => $original->id]), transferReversalHttpInput())->assertSessionHasNoErrors();
    expect($original->fresh()->reversal->team_id)->toBe($team->id);
    expect($foreign->fresh()->reversal)->toBeNull();
});

test('duplicate reversal attempts and reversal groups return clear conflicts without extra audits', function () {
    [$actor, $team, $source, $destination, $original] = transferReversalHttpContext();
    $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, transferReversalHttpInput());
    foreach ([$original, $reversal] as $target) {
        $this->actingAs($actor)->postJson(route('transfers.reverse', ['current_team' => $team->slug, 'transaction_transfer' => $target->id]), transferReversalHttpInput())
            ->assertUnprocessable()->assertJsonPath('errors.transfer.0', 'This transfer has already been reversed or is itself a reversal. Reload it before continuing.');
    }
    $this->assertDatabaseCount('transaction_transfers', 2);
    $this->assertDatabaseCount('account_transactions', 4);
    $this->assertDatabaseCount('transaction_revisions', 4);
});

test('either stale original revision token produces actionable feedback without writes', function (string $role) {
    [$actor, $team, $source, $destination, $original] = transferReversalHttpContext();
    $field = 'expected_'.$role.'_revision';
    $params = ['current_team' => $team->slug, 'transaction_transfer' => $original->id];
    $this->actingAs($actor)->postJson(route('transfers.reverse', $params), transferReversalHttpInput([$field => 2]))
        ->assertUnprocessable()->assertJsonPath('errors.'.$field.'.0', 'This transfer leg has changed. Reload the transfer before trying again.');
    $this->from(route('transfers.show', $params))->post(route('transfers.reverse', $params), transferReversalHttpInput([$field => 2]))
        ->assertRedirect(route('transfers.show', $params))->assertSessionHasErrors($field);
    $this->assertDatabaseCount('transaction_transfers', 1);
    $this->assertDatabaseCount('transaction_revisions', 2);
})->with(['source', 'destination']);

test('reversal form validation rejects missing or injected financial fields', function (array $overrides, string $field) {
    [$actor, $team, $source, $destination, $original] = transferReversalHttpContext();
    $this->actingAs($actor)->postJson(route('transfers.reverse', ['current_team' => $team->slug, 'transaction_transfer' => $original->id]), transferReversalHttpInput($overrides))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('transaction_transfers', 1);
    $this->assertDatabaseCount('account_transactions', 2);
    $this->assertDatabaseCount('transaction_revisions', 2);
})->with([
    'reason' => [['reason' => null], 'reason'],
    'blank reason' => [['reason' => '   '], 'reason'],
    'posting date' => [['posted_date' => null], 'posted_date'],
    'invalid date' => [['posted_date' => '2026-02-30'], 'posted_date'],
    'early date' => [['posted_date' => '2026-10-01'], 'posted_date'],
    'source token' => [['expected_source_revision' => null], 'expected_source_revision'],
    'destination token' => [['expected_destination_revision' => null], 'expected_destination_revision'],
    'account' => [['source_account_id' => 900001], 'source_account_id'],
    'amount' => [['source_amount' => '1'], 'source_amount'],
    'team' => [['team_id' => 900001], 'team_id'],
    'FX' => [['execution_fx' => ['rate' => '83']], 'execution_fx'],
]);

test('archived accounts keep detail history but reject reversal requests', function (bool $sourceSide) {
    [$actor, $team, $source, $destination, $original] = transferReversalHttpContext();
    ($sourceSide ? $source : $destination)->update(['archived_at' => now()]);
    $params = ['current_team' => $team->slug, 'transaction_transfer' => $original->id];
    $this->actingAs($actor)->get(route('transfers.show', $params))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canReverse', false)->where('reversalPreview', null));
    $this->postJson(route('transfers.reverse', $params), transferReversalHttpInput())->assertForbidden();
    $this->assertDatabaseCount('transaction_revisions', 2);
})->with([true, false]);
