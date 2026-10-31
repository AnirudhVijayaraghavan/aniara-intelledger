<?php

use App\Actions\Finance\CancelTransaction;
use App\Actions\Finance\ClassifyTransaction;
use App\Actions\Finance\CreateTransfer;
use App\Actions\Finance\PostTransaction;
use App\Enums\FinancialAccountType;
use App\Enums\TeamRole;
use App\Enums\TransactionKind;
use App\Enums\TransactionRevisionOperation;
use App\Enums\TransactionStatus;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\ExchangeRate;
use App\Models\FinancialAccount;
use App\Models\FinancialInstitution;
use App\Models\Team;
use App\Models\TransactionRevision;
use App\Models\TransactionTransfer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** @return array{User, Team, FinancialAccount, FinancialAccount} */
function transferContext(): array
{
    $actor = User::factory()->create();
    $team = $actor->personalTeam();

    return [$actor, $team,
        FinancialAccount::factory()->create(['team_id' => $team->id]),
        FinancialAccount::factory()->create(['team_id' => $team->id, 'type' => FinancialAccountType::Savings]),
    ];
}

/** @param array<string, mixed> $changes
 * @return array<string, mixed>
 */
function transferInput(FinancialAccount $source, FinancialAccount $destination, array $changes = []): array
{
    return array_replace([
        'source_account_id' => $source->id, 'destination_account_id' => $destination->id,
        'source_amount' => '500.00000000', 'destination_amount' => '500.00000000',
        'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-02',
        'description' => 'Household transfer', 'reason' => 'Manual transfer',
    ], $changes);
}

test('posted transfers preserve native signs economic effects audits and observations', function (FinancialAccountType $sourceType, FinancialAccountType $destinationType, string $sourceAmount, string $destinationAmount) {
    [$actor, $team, $source, $destination] = transferContext();
    $source->update(['type' => $sourceType]);
    $destination->update(['type' => $destinationType]);
    $firstInstitution = FinancialInstitution::factory()->create(['team_id' => $team->id]);
    $secondInstitution = FinancialInstitution::factory()->create(['team_id' => $team->id]);
    $source->update(['financial_institution_id' => $firstInstitution->id]);
    $destination->update(['financial_institution_id' => $secondInstitution->id]);
    $sourceBalance = AccountBalance::factory()->create(['financial_account_id' => $source->id]);
    $destinationBalance = AccountBalance::factory()->create(['financial_account_id' => $destination->id]);
    $balancesBefore = AccountBalance::orderBy('id')->get()->toArray();

    $transfer = app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $destination));

    expect($transfer->sourceTransaction->amount)->toBe($sourceAmount);
    expect($transfer->destinationTransaction->amount)->toBe($destinationAmount);
    expect($source->type->netWorthContribution($transfer->sourceTransaction->amount))->toBe('-500.00000000');
    expect($destination->type->netWorthContribution($transfer->destinationTransaction->amount))->toBe('500.00000000');
    expect($transfer->sourceAccount->is($source))->toBeTrue();
    expect($transfer->destinationAccount->is($destination))->toBeTrue();
    expect($transfer->sourceTransaction->sourceTransfer->is($transfer))->toBeTrue();
    expect($transfer->destinationTransaction->destinationTransfer->is($transfer))->toBeTrue();
    expect($source->outgoingTransfers->sole()->is($transfer))->toBeTrue();
    expect($destination->incomingTransfers->sole()->is($transfer))->toBeTrue();
    expect($team->transactionTransfers->sole()->is($transfer))->toBeTrue();
    expect($transfer->creator->is($actor))->toBeTrue();
    foreach ([$transfer->sourceTransaction, $transfer->destinationTransaction] as $leg) {
        expect($leg->kind)->toBe(TransactionKind::Transfer);
        expect($leg->status)->toBe(TransactionStatus::Posted);
        expect($leg->excluded_from_analytics)->toBeTrue();
        expect($leg->category_id)->toBeNull();
        expect($leg->payee_id)->toBeNull();
        expect($leg->posted_date->toDateString())->toBe('2026-10-02');
        $revision = $leg->revisions()->sole();
        expect($revision->operation)->toBe(TransactionRevisionOperation::Created);
        expect($revision->revision_number)->toBe(1);
        expect($revision->actor_id)->toBe($actor->id);
        expect($revision->reason)->toBe('Manual transfer');
        expect($revision->snapshot)->toBe($leg->fresh()->canonicalSnapshot());
    }
    expect(AccountBalance::orderBy('id')->get()->toArray())->toBe($balancesBefore);
    $this->assertDatabaseCount('transaction_transfers', 1);
    $this->assertDatabaseCount('account_transactions', 2);
    $this->assertDatabaseCount('transaction_revisions', 2);
})->with([
    'checking to savings' => [FinancialAccountType::Checking, FinancialAccountType::Savings, '-500.00000000', '500.00000000'],
    'checking to brokerage' => [FinancialAccountType::Checking, FinancialAccountType::Brokerage, '-500.00000000', '500.00000000'],
    'credit card payment' => [FinancialAccountType::Checking, FinancialAccountType::CreditCard, '-500.00000000', '-500.00000000'],
    'loan payment' => [FinancialAccountType::Checking, FinancialAccountType::Loan, '-500.00000000', '-500.00000000'],
    'liability to asset' => [FinancialAccountType::Loan, FinancialAccountType::Checking, '500.00000000', '500.00000000'],
    'liability to liability' => [FinancialAccountType::CreditCard, FinancialAccountType::Loan, '500.00000000', '-500.00000000'],
]);

test('cross currency transfers keep actual amounts without using household valuation rates', function () {
    [$actor, $team, $source, $destination] = transferContext();
    $destination->update(['currency_code' => 'INR']);
    ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => '99.000000000000000000']);

    $transfer = app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $destination, [
        'source_amount' => '1.00000001', 'destination_amount' => '83.12345678',
    ]));

    expect($transfer->sourceTransaction->amount)->toBe('-1.00000001');
    expect($transfer->sourceTransaction->currency_code)->toBe('USD');
    expect($transfer->destinationTransaction->amount)->toBe('83.12345678');
    expect($transfer->destinationTransaction->currency_code)->toBe('INR');
    expect($transfer->fx_rate)->toBeNull();
    expect($transfer->fx_source)->toBeNull();
    expect($transfer->fx_effective_at)->toBeNull();
    $this->assertDatabaseCount('exchange_rates', 1);
    $this->assertDatabaseCount('account_balances', 0);
});

test('execution provenance preserves direct and reverse quotes with UTC timestamps and decimal strings', function (bool $inverse) {
    [$actor, $team, $source, $destination] = transferContext();
    $destination->update(['currency_code' => 'INR']);
    $fx = [
        'base_currency_code' => $inverse ? 'INR' : 'USD', 'quote_currency_code' => $inverse ? 'USD' : 'INR',
        'rate' => $inverse ? '0.012' : '83.123456789012345678', 'source' => 'Bank execution receipt',
        'effective_at' => '2026-10-01T12:00:00.123456+05:30',
    ];

    $transfer = app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $destination, [
        'source_amount' => $inverse ? '12' : '1', 'destination_amount' => $inverse ? '1000' : '83.12345678', 'execution_fx' => $fx,
    ]));

    expect($transfer->fresh()->fx_rate)->toBe($inverse ? '0.012000000000000000' : '83.123456789012345678');
    expect($transfer->fresh()->fx_source)->toBe('Bank execution receipt');
    expect($transfer->fresh()->fx_base_currency_code)->toBe($fx['base_currency_code']);
    expect($transfer->fresh()->fx_quote_currency_code)->toBe($fx['quote_currency_code']);
    expect($transfer->fresh()->fx_effective_at->format('Y-m-d H:i:s.u'))->toBe('2026-10-01 06:30:00.123456');
    expect(json_decode($transfer->fresh()->toJson(), true)['fx_rate'])->toBe($transfer->fx_rate);
    $this->assertDatabaseCount('exchange_rates', 0);
})->with([false, true]);

test('decimal precision and equal magnitudes tolerate different input scales without rounding', function () {
    [$actor, $team, $source, $destination] = transferContext();

    $transfer = app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $destination, [
        'source_amount' => '9999999999999999.12345678', 'destination_amount' => '9999999999999999.12345678',
    ]));

    expect($transfer->sourceTransaction->amount)->toBe('-9999999999999999.12345678');
    expect($transfer->destinationTransaction->amount)->toBe('9999999999999999.12345678');
    expect($transfer->sourceTransaction->revisions()->sole()->snapshot['amount'])->toBe('-9999999999999999.12345678');
    $scaled = app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $destination, ['source_amount' => '1', 'destination_amount' => '1.00000000']));
    expect($scaled->sourceTransaction->amount)->toBe('-1.00000000');
    expect($scaled->destinationTransaction->amount)->toBe('1.00000000');
});

test('invalid or partial transfer inputs leave no group transactions or revisions', function (array $changes, string $field) {
    [$actor, $team, $source, $destination] = transferContext();

    try {
        app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $destination, $changes));
        test()->fail('Expected validation failure.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }
    $this->assertDatabaseCount('transaction_transfers', 0);
    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_revisions', 0);
})->with([
    'missing source' => [['source_account_id' => null], 'source_account_id'],
    'missing destination' => [['destination_account_id' => null], 'destination_account_id'],
    'missing source amount' => [['source_amount' => null], 'source_amount'],
    'missing destination amount' => [['destination_amount' => null], 'destination_amount'],
    'zero' => [['source_amount' => '0'], 'source_amount'],
    'negative magnitude' => [['destination_amount' => '-500'], 'destination_amount'],
    'float' => [['source_amount' => 500.5], 'source_amount'],
    'integer' => [['source_amount' => 500], 'source_amount'],
    'precision' => [['source_amount' => '0.123456789'], 'source_amount'],
    'overflow' => [['source_amount' => '10000000000000000'], 'source_amount'],
    'mismatch' => [['destination_amount' => '499.99999999'], 'destination_amount'],
    'posting date' => [['posted_date' => null], 'posted_date'],
    'invalid date' => [['transaction_date' => '2026-02-30'], 'transaction_date'],
    'pending' => [['status' => 'pending'], 'status'],
    'currency' => [['source_currency_code' => 'XXX'], 'source_currency_code'],
    'tenant' => [['team_id' => 900001], 'team_id'],
    'actor' => [['created_by' => 900001], 'created_by'],
    'link existing leg' => [['source_transaction_id' => 900001], 'source_transaction_id'],
    'analytics override' => [['excluded_from_analytics' => false], 'excluded_from_analytics'],
    'empty fx' => [['execution_fx' => []], 'execution_fx'],
]);

test('invalid FX observations cannot manufacture a transfer rate', function (array $fx) {
    [$actor, $team, $source, $destination] = transferContext();
    $destination->update(['currency_code' => 'INR']);
    $valid = ['base_currency_code' => 'USD', 'quote_currency_code' => 'INR', 'rate' => '83', 'source' => 'Manual receipt', 'effective_at' => '2026-10-01T12:00:00Z'];

    expect(fn () => app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $destination, [
        'destination_amount' => '41500', 'execution_fx' => array_replace($valid, $fx),
    ])))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_transfers', 0);
})->with([
    [['rate' => '0']], [['rate' => '-83']], [['rate' => 83.0]], [['rate' => '0.1234567890123456789']],
    [['base_currency_code' => 'XXX']], [['quote_currency_code' => 'USD']], [['source' => '']],
    [['effective_at' => '2026-10-01 12:00:00']], [['source' => null]],
]);

test('same currency accounts reject FX provenance and same account transfers are rejected', function () {
    [$actor, $team, $source, $destination] = transferContext();

    expect(fn () => app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $source)))->toThrow(ValidationException::class);
    expect(fn () => app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $destination, [
        'execution_fx' => ['base_currency_code' => 'USD', 'quote_currency_code' => 'INR', 'rate' => '83', 'source' => 'Manual', 'effective_at' => '2026-10-01T12:00:00Z'],
    ])))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('account_transactions', 0);
});

test('authorized owners and admins can create transfers without relying on current team preference', function (TeamRole $role) {
    [$owner, $team, $source, $destination] = transferContext();
    $actor = User::factory()->create();
    $team->members()->attach($actor, ['role' => $role->value]);
    expect($actor->current_team_id)->not->toBe($team->id);

    $transfer = app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $destination));

    expect($transfer->team_id)->toBe($team->id);
    expect($transfer->sourceTransaction->created_by)->toBe($actor->id);
    expect($transfer->destinationTransaction->created_by)->toBe($actor->id);
})->with([TeamRole::Owner, TeamRole::Admin]);

test('members and outsiders cannot create transfers', function (bool $member) {
    [$owner, $team, $source, $destination] = transferContext();
    $actor = User::factory()->create();
    if ($member) {
        $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    }

    expect(fn () => app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $destination)))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_transfers', 0);
})->with([true, false]);

test('foreign and archived accounts are rejected before any legs are saved', function (string $invalid, string $side) {
    [$actor, $team, $source, $destination] = transferContext();
    $target = $side === 'source' ? $source : $destination;
    if ($invalid === 'archived') {
        $target->update(['archived_at' => now()]);
    } else {
        $target = FinancialAccount::factory()->create();
        $target->team->members()->attach($actor, ['role' => TeamRole::Admin->value]);
    }
    $input = transferInput($source, $destination, [$side.'_account_id' => $target->id]);

    expect(fn () => app(CreateTransfer::class)->handle($actor, $team, $input))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_revisions', 0);
})->with(['foreign', 'archived'])->with(['source', 'destination']);

test('failure while recording the destination or group rolls back both legs and all audits', function (string $failure) {
    [$actor, $team, $source, $destination] = transferContext();
    $event = match ($failure) {
        'second leg' => 'eloquent.creating: '.AccountTransaction::class,
        'second audit' => 'eloquent.creating: '.TransactionRevision::class,
        default => 'eloquent.creating: '.TransactionTransfer::class,
    };
    $calls = 0;
    Event::listen($event, function () use (&$calls, $failure): ?bool {
        $calls++;
        if ($failure === 'group veto') {
            return false;
        }
        if ($failure === 'group exception' || $calls === 2) {
            throw new RuntimeException('Transfer persistence failed');
        }

        return null;
    });

    try {
        expect(fn () => app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $destination)))
            ->toThrow($failure === 'group veto' ? DomainException::class : RuntimeException::class);
    } finally {
        Event::forget($event);
    }
    $this->assertDatabaseCount('transaction_transfers', 0);
    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_revisions', 0);
})->with(['second leg', 'second audit', 'group exception', 'group veto']);

test('outer rollback also removes a completed transfer and both creation revisions', function () {
    [$actor, $team, $source, $destination] = transferContext();

    expect(fn () => DB::transaction(function () use ($actor, $team, $source, $destination): void {
        app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $destination));
        throw new RuntimeException('Outer rollback');
    }))->toThrow(RuntimeException::class);
    $this->assertDatabaseCount('transaction_transfers', 0);
    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_revisions', 0);
});

test('ordinary transaction actions cannot independently modify either leg', function (string $action, bool $sourceSide) {
    [$actor, $team, $source, $destination] = transferContext();
    $transfer = app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $destination));
    $leg = $sourceSide ? $transfer->sourceTransaction : $transfer->destinationTransaction;

    expect(fn () => app($action)->handle($actor, $team, $leg, [
        'expected_revision' => 1, 'posted_date' => '2026-10-03', 'description' => 'Independent edit',
    ]))->toThrow(AuthorizationException::class);
    expect($leg->fresh()->kind)->toBe(TransactionKind::Transfer);
    $this->assertDatabaseCount('transaction_revisions', 2);
})->with([PostTransaction::class, CancelTransaction::class, ClassifyTransaction::class])->with([true, false]);

test('direct quiet edits and deletions of transfers and their legs are blocked', function () {
    $transfer = TransactionTransfer::factory()->create();

    expect(fn () => $transfer->update(['fx_source' => 'Rewrite']))->toThrow(DomainException::class);
    expect(fn () => $transfer->deleteQuietly())->toThrow(DomainException::class);
    $leg = $transfer->sourceTransaction;
    $leg->description = 'Rewrite';
    expect(fn () => $leg->saveQuietly())->toThrow(DomainException::class);
    expect(fn () => $leg->delete())->toThrow(DomainException::class);
    expect(fn () => DB::table('account_transactions')->where('id', $leg->id)->delete())->toThrow(QueryException::class);
    $this->assertDatabaseCount('transaction_revisions', 2);
    expect($leg->fresh()->description)->toBe('Recorded purchase');
});

test('a leg cannot be reused in either role', function () {
    $transfer = TransactionTransfer::factory()->create();
    $attributes = $transfer->getAttributes();
    unset($attributes['id']);

    expect(fn () => TransactionTransfer::create($attributes))->toThrow(DomainException::class, 'A transaction can belong to only one transfer.');
    $attributes['source_transaction_id'] = $transfer->destination_transaction_id;
    $attributes['destination_transaction_id'] = $transfer->source_transaction_id;
    $attributes['source_account_id'] = $transfer->destination_account_id;
    $attributes['destination_account_id'] = $transfer->source_account_id;
    expect(fn () => TransactionTransfer::create($attributes))->toThrow(DomainException::class, 'A transaction can belong to only one transfer.');
    $this->assertDatabaseCount('transaction_transfers', 1);
});

test('group creation rejects malformed or unaudited posted movements', function (string $invalid) {
    $group = TransactionTransfer::factory()->make();
    if ($invalid === 'extra revision') {
        $group->sourceTransaction->update(['description' => 'Prior accepted edit']);
    } elseif ($invalid === 'snapshot mismatch') {
        $leg = $group->sourceTransaction;
        DB::table('transaction_revisions')->where('account_transaction_id', $leg->id)->update([
            'snapshot' => json_encode([...$leg->canonicalSnapshot(), 'amount' => '-99.00000000']),
        ]);
    } elseif ($invalid === 'partial FX') {
        $group->fx_rate = '83';
    } else {
        $destination = $invalid === 'mismatched magnitude';
        $accountId = $destination ? $group->destination_account_id : $group->source_account_id;
        $factory = AccountTransaction::factory()->state([
            'financial_account_id' => $accountId, 'kind' => $invalid === 'kind' ? TransactionKind::Expense : TransactionKind::Transfer,
            'amount' => $invalid === 'source sign' ? '100.00000000' : ($destination ? '50.00000000' : '-100.00000000'),
            'excluded_from_analytics' => $invalid !== 'analytics',
        ]);
        $leg = $invalid === 'pending' ? $factory->create() : $factory->posted()->create();
        if ($destination) {
            $group->destination_transaction_id = $leg->id;
        } else {
            $group->source_transaction_id = $leg->id;
        }
    }

    expect(fn () => $group->save())->toThrow(DomainException::class);
    $this->assertDatabaseCount('transaction_transfers', 0);
})->with(['pending', 'kind', 'analytics', 'extra revision', 'snapshot mismatch', 'source sign', 'mismatched magnitude', 'partial FX']);

test('database constraints reject missing legs wrong accounts cross team links duplicate roles and incomplete FX', function (string $invalid) {
    $group = TransactionTransfer::factory()->make();
    $attributes = $group->getAttributes();
    $other = FinancialAccount::factory()->create(['team_id' => $group->team_id]);
    $attributes = array_replace($attributes, match ($invalid) {
        'missing leg' => ['source_transaction_id' => 900001],
        'null leg' => ['source_transaction_id' => null],
        'same account' => ['destination_account_id' => $group->source_account_id, 'destination_transaction_id' => $group->source_transaction_id],
        'wrong account' => ['source_account_id' => $other->id],
        'cross team' => ['team_id' => Team::factory()->create()->id],
        'incomplete fx' => ['fx_rate' => '83.000000000000000000'],
    });

    expect(fn () => DB::table('transaction_transfers')->insert($attributes))->toThrow(QueryException::class);
    $this->assertDatabaseCount('transaction_transfers', 0);
})->with(['missing leg', 'null leg', 'same account', 'wrong account', 'cross team', 'incomplete fx']);

test('database unique constraints prevent duplicate source and destination relationships', function (string $role) {
    $transfer = TransactionTransfer::factory()->create();
    $unclaimed = TransactionTransfer::factory()->make(['team_id' => $transfer->team_id]);
    $attributes = array_replace($unclaimed->getAttributes(), [
        $role.'_transaction_id' => $transfer->getAttribute($role.'_transaction_id'),
        $role.'_account_id' => $transfer->getAttribute($role.'_account_id'),
    ]);

    expect(fn () => DB::table('transaction_transfers')->insert($attributes))->toThrow(QueryException::class);
    $this->assertDatabaseCount('transaction_transfers', 1);
})->with(['source', 'destination']);

test('policies and tenant scopes preserve household read access and immutable transfer history', function (?TeamRole $role, bool $read) {
    $transfer = TransactionTransfer::factory()->create();
    $actor = User::factory()->create();
    if ($role !== null) {
        $transfer->team->members()->attach($actor, ['role' => $role->value]);
    }
    $gate = Gate::forUser($actor);

    expect($gate->allows('view', $transfer))->toBe($read);
    expect($gate->allows('viewAny', [TransactionTransfer::class, $transfer->team]))->toBe($read);
    expect($gate->allows('update', $transfer))->toBeFalse();
    expect($gate->allows('delete', $transfer))->toBeFalse();
    expect(TransactionTransfer::forTeam($actor->personalTeam())->count())->toBe(0);
})->with([[TeamRole::Owner, true], [TeamRole::Admin, true], [TeamRole::Member, true], [null, false]]);

test('archiving accounts and deleting a user preserves transfer history', function () {
    [$actor, $team, $source, $destination] = transferContext();
    $transfer = app(CreateTransfer::class)->handle($actor, $team, transferInput($source, $destination));
    $source->update(['archived_at' => now()]);
    $actor->delete();

    $this->assertModelExists($transfer);
    expect($transfer->fresh()->created_by)->toBeNull();
    expect($transfer->fresh()->sourceTransaction->created_by)->toBeNull();
    expect($transfer->fresh()->sourceTransaction->revisions()->sole()->snapshot['created_by'])->toBe($actor->id);
    expect($source->fresh()->outgoingTransfers)->toHaveCount(1);
    $this->assertDatabaseCount('transaction_revisions', 2);
});
