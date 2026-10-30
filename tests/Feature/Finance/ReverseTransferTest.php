<?php

use App\Actions\Finance\CancelTransaction;
use App\Actions\Finance\ClassifyTransaction;
use App\Actions\Finance\CreateTransfer;
use App\Actions\Finance\PostTransaction;
use App\Actions\Finance\ReverseTransaction;
use App\Actions\Finance\ReverseTransfer;
use App\Enums\FinancialAccountType;
use App\Enums\TeamRole;
use App\Enums\TransactionKind;
use App\Enums\TransactionStatus;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\ExchangeRate;
use App\Models\FinancialAccount;
use App\Models\Team;
use App\Models\TransactionRevision;
use App\Models\TransactionTransfer;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** @return array{User, Team, FinancialAccount, FinancialAccount, TransactionTransfer} */
function reverseTransferContext(array $sourceAttributes = [], array $destinationAttributes = [], array $input = []): array
{
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $source = FinancialAccount::factory()->create(['team_id' => $team->id, ...$sourceAttributes]);
    $destination = FinancialAccount::factory()->create(['team_id' => $team->id, ...$destinationAttributes]);
    $transfer = app(CreateTransfer::class)->handle($actor, $team, [
        'source_account_id' => $source->id, 'destination_account_id' => $destination->id,
        'source_amount' => '500.00000000', 'destination_amount' => '500.00000000',
        'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-02', ...$input,
    ]);

    return [$actor, $team, $source, $destination, $transfer];
}

/** @return array<string, mixed> */
function reverseTransferInput(array $overrides = []): array
{
    return array_replace([
        'expected_source_revision' => 1, 'expected_destination_revision' => 1,
        'posted_date' => '2026-10-03', 'reason' => 'Reverse incorrect household transfer',
    ], $overrides);
}

test('transfer reversals negate both native movements and preserve history audits and economic direction', function (FinancialAccountType $sourceType, FinancialAccountType $destinationType, string $newSource, string $newDestination) {
    $this->freezeTime();
    [$actor, $team, $source, $destination, $original] = reverseTransferContext(['type' => $sourceType], ['type' => $destinationType]);
    AccountBalance::factory()->create(['financial_account_id' => $source->id]);
    AccountBalance::factory()->create(['financial_account_id' => $destination->id]);
    $balances = AccountBalance::orderBy('id')->get()->toArray();
    $groupBefore = $original->fresh()->getAttributes();
    $legsBefore = AccountTransaction::orderBy('id')->get()->toArray();
    $auditsBefore = TransactionRevision::orderBy('id')->get()->toArray();
    $this->travel(1)->seconds();

    $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput());

    expect($reversal->reverses_transfer_id)->toBe($original->id);
    expect($reversal->reversesTransfer->is($original))->toBeTrue();
    expect($original->fresh()->reversal->is($reversal))->toBeTrue();
    expect($reversal->source_account_id)->toBe($destination->id);
    expect($reversal->destination_account_id)->toBe($source->id);
    expect($reversal->sourceTransaction->amount)->toBe($newSource);
    expect($reversal->destinationTransaction->amount)->toBe($newDestination);
    expect($reversal->sourceTransaction->reverses_transaction_id)->toBe($original->destination_transaction_id);
    expect($reversal->destinationTransaction->reverses_transaction_id)->toBe($original->source_transaction_id);
    expect($destination->type->netWorthContribution($newSource))->toBe('-500.00000000');
    expect($source->type->netWorthContribution($newDestination))->toBe('500.00000000');
    foreach ([[$reversal->sourceTransaction, $original->destinationTransaction], [$reversal->destinationTransaction, $original->sourceTransaction]] as [$leg, $originalLeg]) {
        expect((string) BigDecimal::of($leg->amount)->plus($originalLeg->amount))->toBe('0.00000000');
        expect($leg->currency_code)->toBe($originalLeg->currency_code);
        expect($leg->kind)->toBe(TransactionKind::Transfer);
        expect($leg->status)->toBe(TransactionStatus::Posted);
        expect($leg->excluded_from_analytics)->toBeTrue();
        expect($leg->transaction_date->toDateString())->toBe('2026-10-03');
        expect($leg->posted_date->toDateString())->toBe('2026-10-03');
        $revision = $leg->revisions()->sole();
        expect($revision->revision_number)->toBe(1);
        expect($revision->operation->value)->toBe('created');
        expect($revision->actor_id)->toBe($actor->id);
        expect($revision->reason)->toBe('Reverse incorrect household transfer');
        expect($revision->snapshot)->toBe($leg->fresh()->canonicalSnapshot());
    }
    expect($original->fresh()->getAttributes())->toBe($groupBefore);
    expect(AccountTransaction::whereIn('id', [$original->source_transaction_id, $original->destination_transaction_id])->orderBy('id')->get()->toArray())->toBe($legsBefore);
    expect(TransactionRevision::whereIn('account_transaction_id', [$original->source_transaction_id, $original->destination_transaction_id])->orderBy('id')->get()->toArray())->toBe($auditsBefore);
    expect(AccountBalance::orderBy('id')->get()->toArray())->toBe($balances);
    $this->assertDatabaseCount('transaction_transfers', 2);
    $this->assertDatabaseCount('account_transactions', 4);
    $this->assertDatabaseCount('transaction_revisions', 4);
})->with([
    'savings' => [FinancialAccountType::Checking, FinancialAccountType::Savings, '-500.00000000', '500.00000000'],
    'brokerage' => [FinancialAccountType::Checking, FinancialAccountType::Brokerage, '-500.00000000', '500.00000000'],
    'credit card payment' => [FinancialAccountType::Checking, FinancialAccountType::CreditCard, '500.00000000', '500.00000000'],
    'loan payment' => [FinancialAccountType::Checking, FinancialAccountType::Loan, '500.00000000', '500.00000000'],
    'liability to asset' => [FinancialAccountType::Loan, FinancialAccountType::Checking, '-500.00000000', '-500.00000000'],
    'liability to liability' => [FinancialAccountType::CreditCard, FinancialAccountType::Loan, '500.00000000', '-500.00000000'],
]);

test('cross currency reversals preserve exact booked amounts and retain FX only as original provenance', function (bool $inverse) {
    $fx = [
        'base_currency_code' => $inverse ? 'INR' : 'USD', 'quote_currency_code' => $inverse ? 'USD' : 'INR',
        'rate' => $inverse ? '0.012000000000000001' : '83.123456789012345678', 'source' => 'Original execution receipt',
        'effective_at' => '2026-10-01T12:00:00.123456+05:30',
    ];
    [$actor, $team, $source, $destination, $original] = reverseTransferContext([], ['currency_code' => 'INR'], [
        'source_amount' => '9999999999999999.12345678', 'destination_amount' => '1234567890123456.00000001', 'execution_fx' => $fx,
    ]);
    ExchangeRate::factory()->create(['team_id' => $team->id, 'rate' => '99.999999999999999999']);
    $before = $original->fresh()->getAttributes();

    $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput());

    expect($reversal->sourceTransaction->amount)->toBe('-1234567890123456.00000001');
    expect($reversal->sourceTransaction->currency_code)->toBe('INR');
    expect($reversal->destinationTransaction->amount)->toBe('9999999999999999.12345678');
    expect($reversal->destinationTransaction->currency_code)->toBe('USD');
    foreach (['fx_rate', 'fx_base_currency_code', 'fx_quote_currency_code', 'fx_source', 'fx_effective_at'] as $field) {
        expect($reversal->getAttribute($field))->toBeNull();
    }
    expect($reversal->reversesTransfer->fx_rate)->toBe($fx['rate']);
    expect($reversal->reversesTransfer->fx_source)->toBe('Original execution receipt');
    expect($original->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('exchange_rates', 1);
})->with([false, true]);

test('transfer reversal policy and action authorize only household owners and admins', function (?TeamRole $role, bool $allowed) {
    [$owner, $team, $source, $destination, $original] = reverseTransferContext();
    $actor = User::factory()->create();
    if ($role !== null) {
        $team->members()->attach($actor, ['role' => $role->value]);
    }
    expect(Gate::forUser($actor)->allows('reverse', $original))->toBe($allowed);
    if ($allowed) {
        $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput());
        expect($reversal->created_by)->toBe($actor->id);
    } else {
        expect(fn () => app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput()))->toThrow(AuthorizationException::class);
        $this->assertDatabaseCount('transaction_transfers', 1);
        $this->assertDatabaseCount('transaction_revisions', 2);
    }
})->with([[TeamRole::Owner, true], [TeamRole::Admin, true], [TeamRole::Member, false], [null, false]]);

test('transfer reversal uses the explicit authorized team and rejects foreign group ids', function () {
    [$actor, $team, $source, $destination, $original] = reverseTransferContext();
    [$other, $otherTeam, $otherSource, $otherDestination, $foreign] = reverseTransferContext();
    $otherTeam->members()->attach($actor, ['role' => TeamRole::Owner->value]);
    $actor->switchTeam($otherTeam);
    expect(fn () => app(ReverseTransfer::class)->handle($actor, $team, $foreign, reverseTransferInput()))->toThrow(ModelNotFoundException::class);
    $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput());
    expect($reversal->team_id)->toBe($team->id);
    expect($foreign->fresh()->reversal)->toBeNull();
});

test('archived source or destination accounts reject transfer reversal without partial activity', function (bool $sourceSide) {
    [$actor, $team, $source, $destination, $original] = reverseTransferContext();
    ($sourceSide ? $source : $destination)->update(['archived_at' => now()]);
    expect(fn () => app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput()))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('transaction_transfers', 1);
    $this->assertDatabaseCount('account_transactions', 2);
    $this->assertDatabaseCount('transaction_revisions', 2);
})->with([true, false]);

test('invalid reversal data and either stale leg token are rejected before financial writes', function (array $overrides, string $field) {
    [$actor, $team, $source, $destination, $original] = reverseTransferContext();
    try {
        app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput($overrides));
        test()->fail('Expected reversal validation failure.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }
    $this->assertDatabaseCount('transaction_transfers', 1);
    $this->assertDatabaseCount('account_transactions', 2);
    $this->assertDatabaseCount('transaction_revisions', 2);
})->with([
    'missing reason' => [['reason' => null], 'reason'],
    'blank reason' => [['reason' => '   '], 'reason'],
    'long reason' => [['reason' => str_repeat('a', 2001)], 'reason'],
    'missing date' => [['posted_date' => null], 'posted_date'],
    'invalid date' => [['posted_date' => '2026-02-30'], 'posted_date'],
    'earlier date' => [['posted_date' => '2026-10-01'], 'posted_date'],
    'missing source revision' => [['expected_source_revision' => null], 'expected_source_revision'],
    'missing destination revision' => [['expected_destination_revision' => null], 'expected_destination_revision'],
    'stale source revision' => [['expected_source_revision' => 2], 'expected_source_revision'],
    'stale destination revision' => [['expected_destination_revision' => 2], 'expected_destination_revision'],
    'invalid revision' => [['expected_source_revision' => 0], 'expected_source_revision'],
    'account injection' => [['source_account_id' => 99], 'source_account_id'],
    'team injection' => [['team_id' => 99], 'team_id'],
    'amount injection' => [['source_amount' => '99'], 'source_amount'],
    'execution quote injection' => [['execution_fx' => ['rate' => '83']], 'execution_fx'],
    'link injection' => [['reverses_transfer_id' => 99], 'reverses_transfer_id'],
]);

test('duplicate requests and reversal of reversal never create additional legs or revisions', function () {
    [$actor, $team, $source, $destination, $original] = reverseTransferContext();
    $staleCopy = $original->fresh();
    $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput());
    foreach ([$staleCopy, $reversal] as $target) {
        expect(fn () => app(ReverseTransfer::class)->handle($actor, $team, $target, reverseTransferInput()))->toThrow(ValidationException::class, 'This transfer has already been reversed or is itself a reversal.');
    }
    $this->assertDatabaseCount('transaction_transfers', 2);
    $this->assertDatabaseCount('account_transactions', 4);
    $this->assertDatabaseCount('transaction_revisions', 4);
});

test('ordinary transaction actions still reject original and reversal transfer legs', function (string $action) {
    [$actor, $team, $source, $destination, $original] = reverseTransferContext();
    $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput());
    foreach ([$original->sourceTransaction, $original->destinationTransaction, $reversal->sourceTransaction, $reversal->destinationTransaction] as $leg) {
        expect(fn () => app($action)->handle($actor, $team, $leg, ['expected_revision' => 1, 'posted_date' => '2026-10-04', 'reason' => 'Independent mutation']))->toThrow(AuthorizationException::class);
    }
    $this->assertDatabaseCount('transaction_revisions', 4);
})->with([PostTransaction::class, CancelTransaction::class, ClassifyTransaction::class, ReverseTransaction::class]);

test('second leg second audit or group persistence failure rolls back the complete reversal', function (string $failure) {
    [$actor, $team, $source, $destination, $original] = reverseTransferContext();
    $event = 'eloquent.creating: '.match ($failure) {
        'second leg' => AccountTransaction::class,
        'second audit' => TransactionRevision::class,
        default => TransactionTransfer::class,
    };
    $calls = 0;
    Event::listen($event, function () use ($failure, &$calls): ?bool {
        if ($failure === 'group veto') {
            return false;
        }
        if (str_starts_with($failure, 'group') || ++$calls === 2) {
            throw new RuntimeException('Injected reversal failure');
        }

        return null;
    });
    try {
        expect(fn () => app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput()))
            ->toThrow($failure === 'group veto' ? DomainException::class : RuntimeException::class);
    } finally {
        Event::forget($event);
    }
    expect($original->fresh()->reversal)->toBeNull();
    expect($original->sourceTransaction->fresh()->reversal)->toBeNull();
    expect($original->destinationTransaction->fresh()->reversal)->toBeNull();
    $this->assertDatabaseCount('transaction_transfers', 1);
    $this->assertDatabaseCount('account_transactions', 2);
    $this->assertDatabaseCount('transaction_revisions', 2);
})->with(['second leg', 'second audit', 'group exception', 'group veto']);

test('caller rollback removes a completed reversal and both creation audits', function () {
    [$actor, $team, $source, $destination, $original] = reverseTransferContext();
    expect(fn () => DB::transaction(function () use ($actor, $team, $original): void {
        app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput());
        throw new RuntimeException('Caller rollback');
    }))->toThrow(RuntimeException::class, 'Caller rollback');
    $this->assertDatabaseCount('transaction_transfers', 1);
    $this->assertDatabaseCount('account_transactions', 2);
    $this->assertDatabaseCount('transaction_revisions', 2);
});

test('historical creator deletion does not make valid transfer audit provenance unreversible', function () {
    [$creator, $team, $source, $destination, $original] = reverseTransferContext();
    $actor = User::factory()->create();
    $team->members()->attach($actor, ['role' => TeamRole::Owner->value]);
    $creatorId = $creator->id;
    $creator->delete();
    $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput());
    expect($original->sourceTransaction->fresh()->created_by)->toBeNull();
    expect($original->sourceTransaction->revisions()->sole()->snapshot['created_by'])->toBe($creatorId);
    expect($reversal->sourceTransaction->created_by)->toBe($actor->id);
});

test('non posted or audit inconsistent original legs cannot produce a reversal', function (string $invalid) {
    [$actor, $team, $source, $destination, $original] = reverseTransferContext();
    if ($invalid === 'pending' || $invalid === 'cancelled') {
        DB::table('account_transactions')->where('id', $original->source_transaction_id)->update(['status' => $invalid]);
    } else {
        DB::table('account_transactions')->where('id', $original->source_transaction_id)->update(['amount' => '-400.00000000']);
    }
    expect(fn () => app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput()))
        ->toThrow($invalid === 'snapshot' ? DomainException::class : ValidationException::class);
    $this->assertDatabaseCount('transaction_transfers', 1);
    $this->assertDatabaseCount('account_transactions', 2);
    $this->assertDatabaseCount('transaction_revisions', 2);
})->with(['pending', 'cancelled', 'snapshot']);

test('quiet model writes cannot rewrite a reversal group or either reversal leg', function () {
    [$actor, $team, $source, $destination, $original] = reverseTransferContext();
    $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput());
    expect(fn () => $reversal->saveQuietly())->toThrow(DomainException::class);
    expect(fn () => $reversal->deleteQuietly())->toThrow(DomainException::class);
    foreach ([$reversal->sourceTransaction, $reversal->destinationTransaction] as $leg) {
        $leg->description = 'Independent rewrite';
        expect(fn () => $leg->saveQuietly())->toThrow(DomainException::class);
    }
    $this->assertDatabaseCount('transaction_revisions', 4);
});

test('SQLite additive migration preserves populated transfer history and its existing constraints', function () {
    [$actor, $team, $source, $destination, $original] = reverseTransferContext();
    $before = $original->fresh()->getAttributes();
    $migration = require database_path('migrations/2026_10_26_222631_add_reversal_link_to_transaction_transfers_table.php');
    $migration->down();
    $migration->up();
    expect($original->fresh()->getAttributes())->toBe($before);
    expect(fn () => DB::table('transaction_transfers')->where('id', $original->id)->update([
        'destination_account_id' => $source->id, 'destination_transaction_id' => $original->source_transaction_id,
    ]))->toThrow(QueryException::class);
    expect(fn () => DB::table('transaction_transfers')->where('id', $original->id)->update(['fx_rate' => '83']))->toThrow(QueryException::class);
    expect(fn () => DB::table('transaction_transfers')->where('id', $original->id)->update([
        'fx_rate' => '0', 'fx_base_currency_code' => 'USD', 'fx_quote_currency_code' => 'INR',
        'fx_source' => 'Manual receipt', 'fx_effective_at' => now()->format('Y-m-d H:i:s.u'),
    ]))->toThrow(QueryException::class);
    $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput());
    expect($reversal->reverses_transfer_id)->toBe($original->id);
});

test('reversal link constraints reject foreign missing self duplicate and fabricated execution references', function (string $invalid) {
    [$actor, $team, $source, $destination, $original] = reverseTransferContext([], ['currency_code' => 'INR']);
    $reversal = app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput());
    $foreign = TransactionTransfer::factory()->create();
    $changes = match ($invalid) {
        'foreign' => ['reverses_transfer_id' => $foreign->id],
        'missing' => ['reverses_transfer_id' => 99999999],
        'self' => ['reverses_transfer_id' => $reversal->id],
        'duplicate' => ['reverses_transfer_id' => $original->id],
        default => ['fx_base_currency_code' => 'USD', 'fx_quote_currency_code' => 'INR', 'fx_rate' => '83', 'fx_source' => 'Incorrect new quote', 'fx_effective_at' => now()->format('Y-m-d H:i:s.u')],
    };
    $target = $invalid === 'duplicate' ? $foreign : $reversal;
    expect(fn () => DB::table('transaction_transfers')->where('id', $target->id)->update($changes))->toThrow(QueryException::class);
})->with(['foreign', 'missing', 'self', 'duplicate', 'execution fx']);

test('ordinary creation rejects reversal links and migration rollback refuses to erase reversal history', function () {
    [$actor, $team, $source, $destination, $original] = reverseTransferContext();
    expect(fn () => app(CreateTransfer::class)->handle($actor, $team, [
        'source_account_id' => $source->id, 'destination_account_id' => $destination->id, 'source_amount' => '1', 'destination_amount' => '1',
        'transaction_date' => '2026-10-03', 'posted_date' => '2026-10-03', 'reverses_transfer_id' => $original->id,
    ]))->toThrow(ValidationException::class);
    app(ReverseTransfer::class)->handle($actor, $team, $original, reverseTransferInput());
    $migration = require database_path('migrations/2026_10_26_222631_add_reversal_link_to_transaction_transfers_table.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Cannot remove reversal links while transfer reversal history exists.');
    $this->assertDatabaseCount('transaction_transfers', 2);
});
