<?php

use App\Actions\Finance\UpdateFinancialAccount;
use App\Enums\FinancialAccountType;
use App\Enums\TeamRole;
use App\Enums\TransactionCategoryKind;
use App\Enums\TransactionKind;
use App\Enums\TransactionRevisionOperation;
use App\Enums\TransactionStatus;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Payee;
use App\Models\Team;
use App\Models\TransactionCategory;
use App\Models\TransactionRevision;
use App\Models\User;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

test('transaction amounts and canonical audit JSON retain exact native currency precision', function () {
    $actor = User::factory()->create();
    $transaction = AccountTransaction::factory()->posted()->create([
        'amount' => '9999999999999999.12345678',
        'transaction_date' => '2026-10-01',
        'posted_date' => '2026-10-02',
        'recorded_at' => '2026-10-03 12:00:00.123456',
        'created_by' => $actor->id,
    ]);

    $transaction = $transaction->fresh();
    expect($transaction->amount)->toBe('9999999999999999.12345678');
    expect(json_decode($transaction->toJson(), true)['amount'])->toBe('9999999999999999.12345678');
    expect($transaction->recorded_at->format('Y-m-d H:i:s.u'))->toBe('2026-10-03 12:00:00.123456');
    expect($transaction->posted_date->toDateString())->toBe('2026-10-02');
    expect($transaction->transaction_date->toDateString())->toBe('2026-10-01');
    expect($transaction->kind)->toBe(TransactionKind::Expense);
    expect($transaction->status)->toBe(TransactionStatus::Posted);
    expect($transaction->category)->toBeNull();
    expect($transaction->payee)->toBeNull();
    expect($transaction->currency->code)->toBe('USD');
    expect($transaction->creator->is($actor))->toBeTrue();
    $revision = $transaction->revisions->sole();
    expect($revision->snapshot['amount'])->toBe('9999999999999999.12345678');
    expect($revision->snapshot['recorded_at'])->toBe('2026-10-03T12:00:00.123456Z');
    expect($revision->operation)->toBe(TransactionRevisionOperation::Created);
    expect($revision->actor->is($actor))->toBeTrue();
    expect($revision->revision_number)->toBe(1);
});

test('native transaction signs are preserved without changing observed balances', function (FinancialAccountType $type, TransactionKind $kind, string $amount) {
    $account = FinancialAccount::factory()->create(['type' => $type, 'currency_code' => 'INR']);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '250.00000000']);

    $transaction = AccountTransaction::factory()->posted()->create([
        'financial_account_id' => $account->id, 'kind' => $kind, 'amount' => $amount,
    ]);

    expect($transaction->fresh()->amount)->toBe($amount);
    expect($transaction->currency_code)->toBe('INR');
    expect($balance->fresh()->amount)->toBe('250.00000000');
    expect($account->balances()->count())->toBe(1);
    expect($account->transactions()->sole()->is($transaction))->toBeTrue();
})->with([
    'checking salary' => [FinancialAccountType::Checking, TransactionKind::Income, '1000.00000000'],
    'checking purchase' => [FinancialAccountType::Checking, TransactionKind::Expense, '-100.00000000'],
    'card purchase' => [FinancialAccountType::CreditCard, TransactionKind::Expense, '100.00000000'],
    'card payment' => [FinancialAccountType::CreditCard, TransactionKind::Transfer, '-100.00000000'],
    'adjustment' => [FinancialAccountType::Checking, TransactionKind::Adjustment, '0.00000001'],
    'unclassified' => [FinancialAccountType::Checking, TransactionKind::Unclassified, '0.00000000'],
]);

test('non string amounts excess scale and overflow are rejected', function (mixed $amount, string $exception) {
    expect(fn () => AccountTransaction::factory()->create(['amount' => $amount]))->toThrow($exception);
    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_revisions', 0);
})->with([
    'float' => [1.5, InvalidArgumentException::class],
    'integer' => [100, InvalidArgumentException::class],
    'overflow' => ['10000000000000000.00000000', InvalidArgumentException::class],
    'excess precision' => ['0.123456789', RoundingNecessaryException::class],
    'malformed' => ['not-money', NumberFormatException::class],
]);

test('recording timestamps normalize offsets to UTC without losing microseconds', function () {
    $transaction = AccountTransaction::factory()->create(['recorded_at' => '2026-10-03T12:00:00.123456+05:30']);
    $revision = TransactionRevision::factory()->create([
        'account_transaction_id' => $transaction->id, 'recorded_at' => '2026-10-03T12:30:00.654321+05:30',
    ]);

    expect($transaction->fresh()->recorded_at->format('Y-m-d H:i:s.u'))->toBe('2026-10-03 06:30:00.123456');
    expect($revision->fresh()->recorded_at->format('Y-m-d H:i:s.u'))->toBe('2026-10-03 07:00:00.654321');
    expect($revision->fresh()->snapshot['recorded_at'])->toBe('2026-10-03T06:30:00.123456Z');
});

test('an unrecorded transaction may lack a timestamp until saving but cannot produce a canonical audit snapshot', function () {
    $this->travelTo(now()->utc()->setDate(2026, 10, 27)->setTime(12, 30, 45, 123456));
    $transaction = AccountTransaction::factory()->make(['recorded_at' => null, 'amount' => '-100.12345678']);

    expect($transaction->recorded_at)->toBeNull();
    expect(fn () => $transaction->canonicalSnapshot())->toThrow(DomainException::class, 'Transaction snapshots require a valid recording timestamp.');
    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_revisions', 0);

    $transaction->save();
    $transaction->refresh();

    expect($transaction->recorded_at->format('Y-m-d H:i:s.u'))->toBe('2026-10-27 12:30:45.123456');
    expect($transaction->canonicalSnapshot()['recorded_at'])->toBe('2026-10-27T12:30:45.123456Z');
    expect($transaction->revisions()->sole()->snapshot)->toBe($transaction->canonicalSnapshot());
    expect(json_decode($transaction->toJson(), true)['amount'])->toBe('-100.12345678');
    $this->assertDatabaseCount('transaction_revisions', 1);
});

test('invalid recording timestamps during auditing roll back the transaction and its revision', function (bool $creating, ?string $timestamp) {
    $transaction = $creating ? AccountTransaction::factory()->make() : AccountTransaction::factory()->create();
    $before = $creating ? null : $transaction->fresh()->getAttributes();
    $revisions = DB::table('transaction_revisions')->orderBy('id')->get()->toArray();
    $transaction->description = 'Must roll back';
    $event = ($creating ? 'eloquent.created: ' : 'eloquent.updated: ').AccountTransaction::class;
    Event::listen($event, function (AccountTransaction $record) use ($timestamp): void {
        $record->setRawAttributes(array_replace($record->getAttributes(), ['recorded_at' => $timestamp]));
    });

    try {
        expect(fn () => $transaction->save())->toThrow(DomainException::class, 'Transaction snapshots require a valid recording timestamp.');
    } finally {
        Event::forget($event);
    }

    $this->assertDatabaseCount('account_transactions', $creating ? 0 : 1);
    expect(DB::table('transaction_revisions')->orderBy('id')->get()->toArray())->toEqual($revisions);
    if (! $creating) {
        expect($transaction->fresh()->getAttributes())->toBe($before);
    }

    $retry = $creating ? AccountTransaction::factory()->create([
        'financial_account_id' => $transaction->financial_account_id, 'recorded_at' => '2026-10-27T12:30:45.123456Z',
    ]) : $transaction->fresh();
    if (! $creating) {
        $retry->update(['description' => 'Accepted retry']);
    }

    expect($retry->revisions()->pluck('revision_number')->all())->toBe($creating ? [1] : [1, 2]);
    expect($retry->revisions()->get()->last()->snapshot)->toBe($retry->fresh()->canonicalSnapshot());
})->with(['creation' => true, 'modification' => false])->with(['missing' => null, 'invalid' => '0000-01-01 00:00:00']);

test('pending changes and posting append actor attributed revisions while no op saves do not', function () {
    $actor = User::factory()->create();
    $transaction = AccountTransaction::factory()->create(['amount' => '-10.00000000']);
    $recordedAt = $transaction->recorded_at->toISOString();

    $transaction->withRevisionContext($actor, 'Final posted amount')->update([
        'amount' => '-12.00000000', 'status' => TransactionStatus::Posted, 'posted_date' => '2026-10-04',
    ]);
    $transaction->save();

    $revisions = $transaction->fresh()->revisions;
    expect($revisions)->toHaveCount(2);
    expect($revisions[0]->snapshot['amount'])->toBe('-10.00000000');
    expect($revisions[1]->snapshot['amount'])->toBe('-12.00000000');
    expect($revisions[1]->snapshot['status'])->toBe('posted');
    expect($revisions[1]->snapshot['recorded_at'])->toBe($recordedAt);
    expect($revisions[1]->operation)->toBe(TransactionRevisionOperation::Updated);
    expect($revisions[1]->actor_id)->toBe($actor->id);
    expect($revisions[1]->reason)->toBe('Final posted amount');
    expect($revisions[1]->revision_number)->toBe(2);
});

test('accepted metadata changes are audited even when saved quietly', function () {
    $transaction = AccountTransaction::factory()->posted()->create();
    $transaction->description = 'Corrected description';
    $transaction->excluded_from_analytics = true;

    $transaction->saveQuietly();

    expect($transaction->fresh()->revisions)->toHaveCount(2);
    expect($transaction->fresh()->revisions->last()->snapshot['excluded_from_analytics'])->toBeTrue();
    expect($transaction->fresh()->description)->toBe('Corrected description');
});

test('posted financial fields and immutable provenance cannot be overwritten', function (array $change) {
    $transaction = AccountTransaction::factory()->posted()->create();

    expect(fn () => $transaction->update($change))->toThrow(DomainException::class);
    expect($transaction->fresh()->revisions)->toHaveCount(1);
    expect($transaction->fresh()->amount)->toBe('-100.00000000');
})->with([
    'amount' => [['amount' => '-200.00000000']],
    'transaction date' => [['transaction_date' => '2001-01-01']],
    'posting date' => [['posted_date' => '2001-01-01']],
    'recording time' => [['recorded_at' => '2001-01-01 00:00:00']],
    'cancellation' => [['status' => TransactionStatus::Cancelled]],
    'currency' => [['currency_code' => 'INR']],
]);

test('cancelled transactions stay retained and cannot be reposted', function () {
    $transaction = AccountTransaction::factory()->cancelled()->create();

    expect(fn () => $transaction->update(['status' => TransactionStatus::Posted, 'posted_date' => '2026-10-01']))->toThrow(DomainException::class);
    expect($transaction->fresh()->status)->toBe(TransactionStatus::Cancelled);
});

test('stale model updates cannot overwrite concurrently accepted changes', function () {
    $transaction = AccountTransaction::factory()->create(['amount' => '-10.00000001']);
    $stale = $transaction->fresh();
    $transaction->update(['amount' => '-10.00000002']);

    expect(fn () => $stale->update(['description' => 'Stale edit']))->toThrow(DomainException::class);
    expect($transaction->fresh()->amount)->toBe('-10.00000002');
    expect($transaction->fresh()->revisions)->toHaveCount(2);
});

test('separately hydrated date instances permit valid saves without changing exact amounts or recording precision', function () {
    $transaction = AccountTransaction::factory()->posted()->create([
        'amount' => '-9999999999999999.12345678', 'recorded_at' => '2026-10-03T12:00:00.123456+05:30',
    ]);
    $reloaded = $transaction->fresh();
    expect($reloaded->recorded_at)->not->toBe($transaction->recorded_at);

    $reloaded->save();
    expect($reloaded->revisions()->count())->toBe(1);
    $reloaded->update(['description' => 'Accepted metadata', 'excluded_from_analytics' => true]);

    $snapshot = $reloaded->fresh()->canonicalSnapshot();
    expect($snapshot['amount'])->toBe('-9999999999999999.12345678');
    expect($snapshot['recorded_at'])->toBe('2026-10-03T06:30:00.123456Z');
    expect($snapshot['excluded_from_analytics'])->toBeTrue();
    expect($reloaded->revisions()->pluck('revision_number')->all())->toBe([1, 2]);
    expect($reloaded->revisions()->get()->last()->snapshot)->toBe($snapshot);
});

test('a concurrent null to empty description change cannot be silently overwritten', function () {
    $transaction = AccountTransaction::factory()->create(['description' => null]);
    $stale = $transaction->fresh();
    $transaction->update(['description' => '']);

    expect(fn () => $stale->update(['description' => 'Stale overwrite']))
        ->toThrow(DomainException::class, 'Reload the transaction before saving concurrent changes.');

    expect($transaction->fresh()->description)->toBe('');
    expect($transaction->revisions()->pluck('revision_number')->all())->toBe([1, 2]);
});

test('one microsecond of out of band recording time drift rejects a stale save without creating a revision', function () {
    $transaction = AccountTransaction::factory()->create(['recorded_at' => '2026-10-03T12:00:00.123456Z']);
    DB::table('account_transactions')->where('id', $transaction->id)->update(['recorded_at' => '2026-10-03 12:00:00.123457']);

    expect(fn () => $transaction->update(['description' => 'Stale overwrite']))
        ->toThrow(DomainException::class, 'Reload the transaction before saving concurrent changes.');

    expect($transaction->fresh()->recorded_at->format('Y-m-d H:i:s.u'))->toBe('2026-10-03 12:00:00.123457');
    expect($transaction->fresh()->description)->toBe('Recorded purchase');
    expect($transaction->revisions()->count())->toBe(1);
});

test('large decimal movements differing by one minor precision unit remain distinct during stale detection', function () {
    $transaction = AccountTransaction::factory()->create(['amount' => '-9999999999999999.12345678']);
    $stale = $transaction->fresh();
    $transaction->update(['amount' => '-9999999999999999.12345679']);

    expect(fn () => $stale->update(['description' => 'Stale overwrite']))->toThrow(DomainException::class);

    expect($transaction->fresh()->amount)->toBe('-9999999999999999.12345679');
    expect($transaction->revisions()->pluck('revision_number')->all())->toBe([1, 2]);
});

test('audit persistence failure rolls back both transaction creation and modification', function (bool $creating) {
    $transaction = $creating ? AccountTransaction::factory()->make() : AccountTransaction::factory()->create();
    $transaction->amount = '-200.00000000';
    $event = 'eloquent.creating: '.TransactionRevision::class;
    Event::listen($event, fn () => throw new RuntimeException('Audit unavailable'));

    try {
        expect(fn () => $transaction->save())->toThrow(RuntimeException::class, 'Audit unavailable');
    } finally {
        Event::forget($event);
    }

    $this->assertDatabaseCount('account_transactions', $creating ? 0 : 1);
    $this->assertDatabaseCount('transaction_revisions', $creating ? 0 : 1);
    if (! $creating) {
        expect(AccountTransaction::findOrFail($transaction->id)->amount)->toBe('-100.00000000');
    }
})->with([true, false]);

test('ordinary transaction and revision deletion and revision mutation are prohibited', function () {
    $transaction = AccountTransaction::factory()->create();
    $revision = $transaction->revisions->sole();

    expect(fn () => $transaction->delete())->toThrow(DomainException::class);
    expect(fn () => $revision->deleteQuietly())->toThrow(DomainException::class);
    expect(fn () => $revision->update(['reason' => 'Rewrite']))->toThrow(DomainException::class);
    expect(fn () => DB::table('account_transactions')->where('id', $transaction->id)->delete())->toThrow(QueryException::class);
    expect($revision->fresh()->reason)->toBeNull();
    $this->assertModelExists($transaction);
});

test('revision factories append canonical snapshots and reject forged snapshots', function () {
    $revision = TransactionRevision::factory()->create();

    expect($revision->snapshot)->toBe($revision->transaction->canonicalSnapshot());
    expect($revision->revision_number)->toBe(2);
    expect(fn () => TransactionRevision::factory()->create([
        'account_transaction_id' => $revision->account_transaction_id, 'snapshot' => ['amount' => '0'],
    ]))->toThrow(DomainException::class);
});

test('database foreign keys reject cross team references and mismatched account currencies', function (string $field) {
    $transaction = AccountTransaction::factory()->create();
    $other = AccountTransaction::factory()->create();
    $category = TransactionCategory::factory()->create(['team_id' => $other->team_id]);
    $payee = Payee::factory()->create(['team_id' => $other->team_id]);
    $value = match ($field) {
        'team_id' => $other->team_id,
        'financial_account_id' => $other->financial_account_id,
        'category_id' => $category->id,
        'payee_id' => $payee->id,
        'currency_code' => 'INR',
        default => $other->id,
    };
    $attributes = array_replace($transaction->getAttributes(), ['id' => 900001, $field => $value]);

    expect(fn () => DB::table('account_transactions')->insert($attributes))->toThrow(QueryException::class);
})->with(['team_id', 'financial_account_id', 'currency_code', 'category_id', 'payee_id', 'reverses_transaction_id', 'replaces_transaction_id']);

test('correction links stay within an account and allow a single reversal and replacement', function () {
    $original = AccountTransaction::factory()->posted()->create();
    $otherAccount = FinancialAccount::factory()->create(['team_id' => $original->team_id]);

    expect(fn () => AccountTransaction::factory()->create([
        'financial_account_id' => $otherAccount->id, 'reverses_transaction_id' => $original->id,
    ]))->toThrow(QueryException::class);

    $reversal = AccountTransaction::factory()->posted()->create([
        'financial_account_id' => $original->financial_account_id, 'amount' => '100.00000000',
        'reverses_transaction_id' => $original->id,
    ]);
    $replacement = AccountTransaction::factory()->posted()->create([
        'financial_account_id' => $original->financial_account_id, 'replaces_transaction_id' => $original->id,
    ]);

    expect($reversal->reversesTransaction->is($original))->toBeTrue();
    expect($replacement->replacesTransaction->is($original))->toBeTrue();
    expect(fn () => AccountTransaction::factory()->create([
        'financial_account_id' => $original->financial_account_id, 'reverses_transaction_id' => $original->id,
    ]))->toThrow(QueryException::class);
});

test('posting date is mandatory at the model and database boundaries', function () {
    expect(fn () => AccountTransaction::factory()->create(['status' => TransactionStatus::Posted]))->toThrow(DomainException::class);

    $transaction = AccountTransaction::factory()->create();
    expect(fn () => DB::table('account_transactions')->where('id', $transaction->id)->update(['status' => 'posted']))->toThrow(QueryException::class);
});

test('revision and category parent foreign keys reject cross team references', function () {
    $transaction = AccountTransaction::factory()->create();
    $other = Team::factory()->create();
    $revision = $transaction->revisions->sole();
    $attributes = array_replace($revision->getAttributes(), [
        'id' => 900001, 'team_id' => $other->id, 'revision_number' => 2,
    ]);

    expect(fn () => DB::table('transaction_revisions')->insert($attributes))->toThrow(QueryException::class);
    $parent = TransactionCategory::factory()->create();
    expect(fn () => DB::table('transaction_categories')->insert([
        'team_id' => $other->id, 'name' => 'Foreign parent', 'kind' => 'expense', 'parent_id' => $parent->id,
    ]))->toThrow(QueryException::class);
});

test('categories and payees are optional archived references with scoped relationships', function () {
    $transaction = AccountTransaction::factory()->create();
    $team = $transaction->team;
    $parent = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $child = TransactionCategory::factory()->create(['team_id' => $team->id, 'parent_id' => $parent->id]);
    $payee = Payee::factory()->create(['team_id' => $team->id]);

    $transaction->update(['category_id' => $child->id, 'payee_id' => $payee->id]);
    $child->update(['archived_at' => now()]);
    $payee->update(['archived_at' => now()]);

    expect($transaction->fresh()->category->is($child))->toBeTrue();
    expect($transaction->fresh()->payee->is($payee))->toBeTrue();
    expect($child->parent->is($parent))->toBeTrue();
    expect($parent->children->sole()->is($child))->toBeTrue();
    expect($child->transactions->sole()->is($transaction))->toBeTrue();
    expect($payee->transactions->sole()->is($transaction))->toBeTrue();
    expect($team->accountTransactions->sole()->is($transaction))->toBeTrue();
    expect($team->transactionRevisions)->toHaveCount(2);
    expect($team->transactionCategories)->toHaveCount(2);
    expect($team->payees->sole()->is($payee))->toBeTrue();
    expect(fn () => $child->delete())->toThrow(DomainException::class);
    expect(fn () => $payee->delete())->toThrow(DomainException::class);
    expect(fn () => $child->update(['kind' => TransactionCategoryKind::Income]))->toThrow(DomainException::class);
});

test('numeric reference strings produce canonical integer identifiers in audit snapshots', function () {
    $account = FinancialAccount::factory()->create();
    $category = TransactionCategory::factory()->create(['team_id' => $account->team_id]);
    $transaction = AccountTransaction::factory()->create([
        'financial_account_id' => (string) $account->id, 'team_id' => (string) $account->team_id,
        'category_id' => (string) $category->id,
    ]);

    $transaction->update(['description' => 'Accepted edit']);

    expect($transaction->fresh()->revisions)->toHaveCount(2);
    expect($transaction->fresh()->revisions->sole(fn ($revision) => $revision->revision_number === 1)->snapshot['category_id'])->toBe($category->id);
});

test('stale category classification cannot produce an incompatible parent link', function () {
    $category = TransactionCategory::factory()->create();
    $stale = $category->fresh();
    $parent = TransactionCategory::factory()->create(['team_id' => $category->team_id]);
    $category->update(['kind' => TransactionCategoryKind::Income]);

    expect(fn () => $stale->update(['parent_id' => $parent->id]))->toThrow(DomainException::class);
    expect($category->fresh()->parent_id)->toBeNull();
});

test('category hierarchy rejects self parenting cycles depth and classification mismatch', function (string $invalid) {
    $root = TransactionCategory::factory()->create();
    $child = TransactionCategory::factory()->create(['team_id' => $root->team_id, 'parent_id' => $root->id]);

    expect(fn () => match ($invalid) {
        'self' => $root->update(['parent_id' => $root->id]),
        'cycle' => $root->update(['parent_id' => $child->id]),
        'depth' => TransactionCategory::factory()->create(['team_id' => $root->team_id, 'parent_id' => $child->id]),
        'kind' => TransactionCategory::factory()->create(['team_id' => $root->team_id, 'kind' => TransactionCategoryKind::Income, 'parent_id' => $root->id]),
    })->toThrow(DomainException::class);
})->with(['self', 'cycle', 'depth', 'kind']);

test('transactions reject categories with a different classification', function () {
    $transaction = AccountTransaction::factory()->create();
    $category = TransactionCategory::factory()->create(['team_id' => $transaction->team_id, 'kind' => TransactionCategoryKind::Income]);

    expect(fn () => $transaction->update(['category_id' => $category->id]))->toThrow(DomainException::class);
    expect($transaction->fresh()->category_id)->toBeNull();
    expect($transaction->fresh()->revisions)->toHaveCount(1);
});

test('transaction history freezes account currency and classification but permits compatible account types', function (array $change) {
    $transaction = AccountTransaction::factory()->cancelled()->create();
    $account = $transaction->account;

    expect(fn () => $account->update($change))->toThrow(DomainException::class);
    $account->refresh()->update(['type' => FinancialAccountType::Savings]);
    expect($account->fresh()->type)->toBe(FinancialAccountType::Savings);
    expect($account->fresh()->currency_code)->toBe('USD');
    expect($account->balances()->count())->toBe(0);
})->with([
    'currency' => [['currency_code' => 'INR']],
    'classification' => [['type' => FinancialAccountType::CreditCard]],
]);

test('account updates validate transaction history before attempting incompatible changes', function () {
    $actor = User::factory()->create();
    $account = FinancialAccount::factory()->create(['team_id' => $actor->personalTeam()->id]);
    AccountTransaction::factory()->create(['financial_account_id' => $account->id]);
    $owner = $account->owners()->sole();

    expect(fn () => app(UpdateFinancialAccount::class)->handle($actor, $account->team, $account, [
        'name' => $account->name, 'type' => 'checking', 'currency_code' => 'INR',
        'owners' => [['id' => $owner->id, 'owner_name' => $owner->owner_name]],
    ]))->toThrow(ValidationException::class, 'Currency cannot change after transaction history exists.');
    expect($account->fresh()->currency_code)->toBe('USD');
});

test('stale account types cannot bypass classification immutability after transaction recording', function () {
    $account = FinancialAccount::factory()->create();
    $stale = $account->fresh();
    $account->update(['type' => FinancialAccountType::CreditCard]);
    AccountTransaction::factory()->create(['financial_account_id' => $account->id]);

    expect(fn () => $stale->update(['type' => FinancialAccountType::Savings]))->toThrow(DomainException::class);
    expect($account->fresh()->type)->toBe(FinancialAccountType::CreditCard);
});

test('explicit scopes isolate all transaction foundation models', function () {
    $transaction = AccountTransaction::factory()->create();
    $other = Team::factory()->create();
    $category = TransactionCategory::factory()->create(['team_id' => $transaction->team_id]);
    $payee = Payee::factory()->create(['team_id' => $transaction->team_id]);

    foreach ([AccountTransaction::class, TransactionRevision::class, TransactionCategory::class, Payee::class] as $model) {
        expect($model::forTeam($other)->count())->toBe(0);
    }
    expect(TransactionCategory::forTeam($transaction->team)->sole()->is($category))->toBeTrue();
    expect(Payee::forTeam($transaction->team)->sole()->is($payee))->toBeTrue();
});

test('transaction policies use membership rather than current team preference', function (?TeamRole $role, bool $read, bool $manage) {
    $actor = User::factory()->create();
    $transaction = AccountTransaction::factory()->create();
    $team = $transaction->team;
    if ($role !== null) {
        $team->members()->attach($actor, ['role' => $role->value]);
    }
    $category = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $payee = Payee::factory()->create(['team_id' => $team->id]);
    $gate = Gate::forUser($actor);

    expect($actor->current_team_id)->not->toBe($team->id);
    foreach ([$transaction, $category, $payee, $transaction->revisions->sole()] as $record) {
        expect($gate->allows('view', $record))->toBe($read);
        expect($gate->allows('delete', $record))->toBeFalse();
        expect($gate->allows('update', $record))->toBe($record instanceof TransactionRevision ? false : $manage);
    }
    foreach ([AccountTransaction::class, TransactionCategory::class, Payee::class, TransactionRevision::class] as $model) {
        expect($gate->allows('viewAny', [$model, $team]))->toBe($read);
    }
    expect($gate->allows('create', [AccountTransaction::class, $transaction->account]))->toBe($manage);
    expect($gate->allows('create', [TransactionCategory::class, $team]))->toBe($manage);
    expect($gate->allows('create', [Payee::class, $team]))->toBe($manage);
    expect($gate->allows('create', [TransactionRevision::class, $team]))->toBeFalse();
})->with([
    'owner' => [TeamRole::Owner, true, true],
    'admin' => [TeamRole::Admin, true, true],
    'member' => [TeamRole::Member, true, false],
    'outsider' => [null, false, false],
]);

test('archived accounts retain transaction history without accepting new changes', function () {
    $actor = User::factory()->create();
    $transaction = AccountTransaction::factory()->create();
    $account = $transaction->account;
    $account->team->members()->attach($actor, ['role' => TeamRole::Admin->value]);
    $account->update(['archived_at' => now()]);

    expect(Gate::forUser($actor)->allows('view', $transaction))->toBeTrue();
    expect(Gate::forUser($actor)->allows('create', [AccountTransaction::class, $account]))->toBeFalse();
    expect(Gate::forUser($actor)->allows('update', $transaction->fresh()))->toBeFalse();
    expect(fn () => $transaction->update(['description' => 'Edit']))->toThrow(DomainException::class);
    expect(fn () => AccountTransaction::factory()->create(['financial_account_id' => $account->id]))->toThrow(DomainException::class);
    expect($account->fresh()->transactions)->toHaveCount(1);
    expect($transaction->fresh()->revisions)->toHaveCount(1);
});

test('user deletion preserves transaction records snapshots and audit history', function () {
    $actor = User::factory()->create();
    $transaction = AccountTransaction::factory()->create(['created_by' => $actor->id]);

    $actor->delete();

    expect($transaction->fresh()->created_by)->toBeNull();
    expect($transaction->fresh()->revisions->sole()->actor_id)->toBeNull();
    expect($transaction->fresh()->revisions->sole()->snapshot['created_by'])->toBe($actor->id);
    $this->assertModelExists($transaction);
    expect(fn () => $transaction->account->delete())->toThrow(QueryException::class);
});
