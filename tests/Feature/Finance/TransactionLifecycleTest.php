<?php

use App\Actions\Finance\CancelTransaction;
use App\Actions\Finance\ClassifyTransaction;
use App\Actions\Finance\CreateTransaction;
use App\Actions\Finance\PostTransaction;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/** @return array{User, Team, FinancialAccount} */
function transactionLifecycleContext(): array
{
    $actor = User::factory()->create();
    $team = $actor->personalTeam();

    return [$actor, $team, FinancialAccount::factory()->create(['team_id' => $team->id])];
}

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function manualTransactionInput(array $overrides = []): array
{
    return array_replace(['amount' => '-10.12345678', 'transaction_date' => '2026-10-01', 'kind' => 'expense'], $overrides);
}

test('pending creation records explicit actor native currency and exactly one canonical revision', function () {
    $this->travelTo(now()->utc()->setDate(2026, 10, 26)->setTime(12, 30, 45, 123456));
    [$actor, $team, $account] = transactionLifecycleContext();
    $account->update(['currency_code' => 'INR', 'type' => FinancialAccountType::CreditCard]);
    $this->actingAs(User::factory()->create());

    $transaction = app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput([
        'amount' => '9999999999999999.12345678', 'reason' => 'Manual purchase',
    ]));

    expect($transaction->status)->toBe(TransactionStatus::Pending);
    expect($transaction->amount)->toBe('9999999999999999.12345678');
    expect($transaction->currency_code)->toBe('INR');
    expect($transaction->created_by)->toBe($actor->id);
    expect($transaction->recorded_at->format('Y-m-d H:i:s.u'))->toBe('2026-10-26 12:30:45.123456');
    expect($transaction->posted_date)->toBeNull();
    $revision = $transaction->revisions()->sole();
    expect($revision->actor_id)->toBe($actor->id);
    expect($revision->reason)->toBe('Manual purchase');
    expect($revision->revision_number)->toBe(1);
    expect($revision->operation)->toBe(TransactionRevisionOperation::Created);
    expect($revision->snapshot)->toBe($transaction->fresh()->canonicalSnapshot());
    $this->assertDatabaseCount('account_transactions', 1);
    $this->assertDatabaseCount('transaction_revisions', 1);
    $this->assertDatabaseCount('account_balances', 0);
});

test('posted creation resolves optional household category and payee without altering observations', function () {
    [$actor, $team, $account] = transactionLifecycleContext();
    $category = TransactionCategory::factory()->create(['team_id' => $team->id, 'kind' => TransactionCategoryKind::Income]);
    $payee = Payee::factory()->create(['team_id' => $team->id]);
    $observation = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '500.00000000']);
    $before = $observation->fresh()->getRawOriginal();

    $transaction = app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput([
        'amount' => '1000', 'kind' => 'income', 'status' => 'posted', 'posted_date' => '2026-10-02',
        'category_id' => (string) $category->id, 'payee_id' => (string) $payee->id,
    ]));

    expect($transaction->status)->toBe(TransactionStatus::Posted);
    expect($transaction->amount)->toBe('1000.00000000');
    expect($transaction->category->is($category))->toBeTrue();
    expect($transaction->payee->is($payee))->toBeTrue();
    expect($transaction->posted_date->toDateString())->toBe('2026-10-02');
    expect($transaction->revisions()->sole()->snapshot)->toBe($transaction->fresh()->canonicalSnapshot());
    expect($observation->fresh()->getRawOriginal())->toBe($before);
    $this->assertDatabaseCount('transaction_revisions', 1);
    $this->assertDatabaseCount('account_balances', 1);
});

test('invalid manual creation leaves no transaction or audit history', function (array $change, string $field) {
    [$actor, $team, $account] = transactionLifecycleContext();

    try {
        app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput($change));
        test()->fail('Expected validation failure.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }

    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_revisions', 0);
})->with([
    'float amount' => [['amount' => 1.25], 'amount'],
    'integer amount' => [['amount' => 100], 'amount'],
    'extra precision' => [['amount' => '0.123456789'], 'amount'],
    'overflow' => [['amount' => '10000000000000000'], 'amount'],
    'formatted amount' => [['amount' => '1,000.00'], 'amount'],
    'exponent amount' => [['amount' => '1e3'], 'amount'],
    'invalid transaction date' => [['transaction_date' => '2026-02-30'], 'transaction_date'],
    'missing posting date' => [['status' => 'posted'], 'posted_date'],
    'invalid posting date' => [['status' => 'posted', 'posted_date' => 'yesterday'], 'posted_date'],
    'pending posting date' => [['posted_date' => '2026-10-02'], 'posted_date'],
    'cancelled creation' => [['status' => 'cancelled'], 'status'],
    'unknown kind' => [['kind' => 'deposit'], 'kind'],
    'currency injection' => [['currency_code' => 'INR'], 'currency_code'],
    'tenant injection' => [['team_id' => 900001], 'team_id'],
    'recording time injection' => [['recorded_at' => '2001-01-01'], 'recorded_at'],
    'actor injection' => [['created_by' => 900001], 'created_by'],
    'reversal execution' => [['reverses_transaction_id' => 900001], 'reverses_transaction_id'],
    'replacement execution' => [['replaces_transaction_id' => 900001], 'replaces_transaction_id'],
]);

test('posting accepts final pending financial fields in a single attributed revision', function () {
    [$actor, $team, $account] = transactionLifecycleContext();
    $transaction = app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput());
    $recordedAt = $transaction->recorded_at->toISOString();
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);

    $posted = app(PostTransaction::class)->handle($actor, $team, $transaction, [
        'expected_revision' => 1, 'amount' => '-12.12345678', 'transaction_date' => '2026-10-02',
        'posted_date' => '2026-10-03', 'description' => 'Final purchase', 'reason' => 'Final amount confirmed',
    ]);

    expect($posted->amount)->toBe('-12.12345678');
    expect($posted->status)->toBe(TransactionStatus::Posted);
    expect($posted->transaction_date->toDateString())->toBe('2026-10-02');
    expect($posted->posted_date->toDateString())->toBe('2026-10-03');
    expect($posted->recorded_at->toISOString())->toBe($recordedAt);
    $revisions = $posted->revisions()->get();
    expect($revisions)->toHaveCount(2);
    expect($revisions[0]->snapshot['amount'])->toBe('-10.12345678');
    expect($revisions[1]->snapshot)->toBe($posted->fresh()->canonicalSnapshot());
    expect($revisions[1]->operation)->toBe(TransactionRevisionOperation::Updated);
    expect($revisions[1]->reason)->toBe('Final amount confirmed');
    expect($revisions[1]->actor_id)->toBe($actor->id);
    expect($revisions[1]->revision_number)->toBe(2);
    expect($balance->fresh()->amount)->toBe('100.00000000');
    $this->assertDatabaseCount('account_balances', 1);
});

test('pending cancellation retains financial fields and audit history', function () {
    [$actor, $team, $account] = transactionLifecycleContext();
    $transaction = app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput());

    $cancelled = app(CancelTransaction::class)->handle($actor, $team, $transaction, ['expected_revision' => 1, 'reason' => 'Authorization released']);

    expect($cancelled->status)->toBe(TransactionStatus::Cancelled);
    expect($cancelled->amount)->toBe('-10.12345678');
    expect($cancelled->posted_date)->toBeNull();
    expect($cancelled->revisions()->get()->pluck('revision_number')->all())->toBe([1, 2]);
    expect($cancelled->revisions()->get()->last()->snapshot['status'])->toBe('cancelled');
    expect($cancelled->revisions()->get()->last()->reason)->toBe('Authorization released');
    $this->assertModelExists($transaction);
});

test('posted classification updates metadata without touching immutable financial fields', function () {
    [$actor, $team, $account] = transactionLifecycleContext();
    $transaction = app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput([
        'kind' => 'unclassified', 'status' => 'posted', 'posted_date' => '2026-10-02',
    ]));
    $before = $transaction->fresh()->only(['amount', 'currency_code', 'financial_account_id', 'recorded_at', 'transaction_date', 'posted_date', 'status']);
    $category = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $payee = Payee::factory()->create(['team_id' => $team->id]);

    $classified = app(ClassifyTransaction::class)->handle($actor, $team, $transaction, [
        'expected_revision' => '1', 'kind' => 'expense', 'category_id' => $category->id, 'payee_id' => $payee->id,
        'excluded_from_analytics' => true, 'description' => 'Household purchase', 'reason' => 'Reviewed',
    ]);

    expect($classified->fresh()->only(array_keys($before)))->toEqual($before);
    expect($classified->kind)->toBe(TransactionKind::Expense);
    expect($classified->category_id)->toBe($category->id);
    expect($classified->payee_id)->toBe($payee->id);
    expect($classified->excluded_from_analytics)->toBeTrue();
    expect($classified->revisions()->count())->toBe(2);
    expect($classified->revisions()->get()->last()->snapshot)->toBe($classified->fresh()->canonicalSnapshot());
    expect($classified->revisions()->get()->last()->reason)->toBe('Reviewed');
});

test('classification changes require clearing an incompatible existing category and permit optional reference removal', function () {
    [$actor, $team, $account] = transactionLifecycleContext();
    $category = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $payee = Payee::factory()->create(['team_id' => $team->id]);
    $transaction = app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput(['category_id' => $category->id, 'payee_id' => $payee->id]));

    expect(fn () => app(ClassifyTransaction::class)->handle($actor, $team, $transaction, ['expected_revision' => 1, 'kind' => 'income']))
        ->toThrow(ValidationException::class, 'The category must match the transaction kind.');

    $classified = app(ClassifyTransaction::class)->handle($actor, $team, $transaction, [
        'expected_revision' => 1, 'kind' => 'income', 'category_id' => null, 'payee_id' => null,
    ]);
    expect($classified->category_id)->toBeNull();
    expect($classified->payee_id)->toBeNull();
    expect($classified->kind)->toBe(TransactionKind::Income);
    expect($classified->revisions()->count())->toBe(2);
});

test('invalid lifecycle transitions cannot change transactions or append revisions', function (string $action, TransactionStatus $status) {
    [$actor, $team, $account] = transactionLifecycleContext();
    $transaction = AccountTransaction::factory()->create([
        'financial_account_id' => $account->id, 'status' => $status,
        'posted_date' => $status === TransactionStatus::Posted ? '2026-10-02' : null,
    ]);
    $input = ['expected_revision' => 1];
    if ($action === PostTransaction::class) {
        $input['posted_date'] = '2026-10-03';
    }

    expect(fn () => app($action)->handle($actor, $team, $transaction, $input))->toThrow(ValidationException::class);
    expect($transaction->fresh()->status)->toBe($status);
    expect($transaction->fresh()->revisions()->count())->toBe(1);
})->with([
    'post posted' => [PostTransaction::class, TransactionStatus::Posted],
    'post cancelled' => [PostTransaction::class, TransactionStatus::Cancelled],
    'cancel posted' => [CancelTransaction::class, TransactionStatus::Posted],
    'cancel cancelled' => [CancelTransaction::class, TransactionStatus::Cancelled],
    'classify cancelled' => [ClassifyTransaction::class, TransactionStatus::Cancelled],
]);

test('posting requires a valid posting date and classification cannot modify financial values', function (string $action, array $input) {
    [$actor, $team, $account] = transactionLifecycleContext();
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id]);

    expect(fn () => app($action)->handle($actor, $team, $transaction, ['expected_revision' => 1, ...$input]))->toThrow(ValidationException::class);
    expect($transaction->fresh()->revisions()->count())->toBe(1);
})->with([
    'missing posting date' => [PostTransaction::class, []],
    'invalid posting date' => [PostTransaction::class, ['posted_date' => '2026-02-30']],
    'amount' => [ClassifyTransaction::class, ['amount' => '100']],
    'transaction date' => [ClassifyTransaction::class, ['transaction_date' => '2026-10-02']],
    'posting date' => [ClassifyTransaction::class, ['posted_date' => '2026-10-02']],
    'status' => [ClassifyTransaction::class, ['status' => 'posted']],
    'currency' => [ClassifyTransaction::class, ['currency_code' => 'INR']],
    'cancellation amount' => [CancelTransaction::class, ['amount' => '100']],
]);

test('stale revision tokens are rejected even though actions reload the transaction', function (string $action) {
    [$actor, $team, $account] = transactionLifecycleContext();
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id]);
    $stale = $transaction->fresh();
    $transaction->update(['description' => 'Concurrent accepted edit']);
    $input = ['expected_revision' => 1];
    if ($action === PostTransaction::class) {
        $input['posted_date'] = '2026-10-02';
    }

    expect(fn () => app($action)->handle($actor, $team, $stale, $input))
        ->toThrow(ValidationException::class, 'This transaction has changed. Reload it before trying again.');
    expect($transaction->fresh()->description)->toBe('Concurrent accepted edit');
    expect($transaction->fresh()->status)->toBe(TransactionStatus::Pending);
    expect($transaction->fresh()->revisions()->count())->toBe(2);
})->with([PostTransaction::class, CancelTransaction::class, ClassifyTransaction::class]);

test('revision tokens reject intervening changes that restore identical field values', function () {
    [$actor, $team, $account] = transactionLifecycleContext();
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id, 'description' => 'Original']);
    $transaction->update(['description' => 'Changed']);
    $transaction->update(['description' => 'Original']);

    expect(fn () => app(ClassifyTransaction::class)->handle($actor, $team, $transaction, ['expected_revision' => 1, 'kind' => 'income']))
        ->toThrow(ValidationException::class, 'This transaction has changed.');
    expect($transaction->fresh()->revisions()->count())->toBe(3);
});

test('mutations require an explicit valid expected revision', function (mixed $revision) {
    [$actor, $team, $account] = transactionLifecycleContext();
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id]);

    expect(fn () => app(ClassifyTransaction::class)->handle($actor, $team, $transaction, ['expected_revision' => $revision, 'kind' => 'income']))
        ->toThrow(ValidationException::class);
    expect($transaction->fresh()->revisions()->count())->toBe(1);
})->with([null, 0, -1, 'invalid']);

test('owners and admins may execute the complete manual lifecycle in an explicitly selected household', function (TeamRole $role) {
    [$owner, $team, $account] = transactionLifecycleContext();
    $actor = User::factory()->create();
    $team->members()->attach($actor, ['role' => $role->value]);
    expect($actor->current_team_id)->not->toBe($team->id);

    $transaction = app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput());
    $posted = app(PostTransaction::class)->handle($actor, $team, $transaction, ['expected_revision' => 1, 'posted_date' => '2026-10-02']);
    $classified = app(ClassifyTransaction::class)->handle($actor, $team, $posted, ['expected_revision' => 2, 'excluded_from_analytics' => true]);
    $pending = app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput());
    $cancelled = app(CancelTransaction::class)->handle($actor, $team, $pending, ['expected_revision' => 1]);

    expect($classified->revisions()->get()->pluck('revision_number')->all())->toBe([1, 2, 3]);
    expect($classified->revisions()->get()->pluck('actor_id')->unique()->all())->toBe([$actor->id]);
    expect($cancelled->status)->toBe(TransactionStatus::Cancelled);
    $this->assertDatabaseCount('transaction_revisions', 5);
})->with([TeamRole::Owner, TeamRole::Admin]);

test('member and outsider actors cannot execute transaction actions', function (string $action, bool $member) {
    [$owner, $team, $account] = transactionLifecycleContext();
    $actor = User::factory()->create();
    if ($member) {
        $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    }
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id]);
    $target = $action === CreateTransaction::class ? $account : $transaction;

    expect(fn () => app($action)->handle($actor, $team, $target, []))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('account_transactions', 1);
    $this->assertDatabaseCount('transaction_revisions', 1);
})->with([CreateTransaction::class, PostTransaction::class, CancelTransaction::class, ClassifyTransaction::class])->with([true, false]);

test('cross team accounts and transactions cannot be used through an authorized team', function (string $action) {
    [$actor, $team] = transactionLifecycleContext();
    $foreign = AccountTransaction::factory()->create();
    $target = $action === CreateTransaction::class ? $foreign->account : $foreign;

    expect(fn () => app($action)->handle($actor, $team, $target, []))->toThrow(ModelNotFoundException::class);
    expect($foreign->fresh()->revisions()->count())->toBe(1);
})->with([CreateTransaction::class, PostTransaction::class, CancelTransaction::class, ClassifyTransaction::class]);

test('archived accounts reject creation posting cancellation and classification', function (string $action) {
    [$actor, $team, $account] = transactionLifecycleContext();
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id]);
    $account->update(['archived_at' => now()]);
    $target = $action === CreateTransaction::class ? $account : $transaction;

    expect(fn () => app($action)->handle($actor, $team, $target, []))->toThrow(AuthorizationException::class);
    expect($transaction->fresh()->status)->toBe(TransactionStatus::Pending);
    $this->assertDatabaseCount('transaction_revisions', 1);
})->with([CreateTransaction::class, PostTransaction::class, CancelTransaction::class, ClassifyTransaction::class]);

test('category and payee validation reject foreign references and incompatible category kinds', function (string $action, string $reference) {
    [$actor, $team, $account] = transactionLifecycleContext();
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id]);
    $category = TransactionCategory::factory()->create($reference === 'kind' ? ['team_id' => $team->id, 'kind' => TransactionCategoryKind::Income] : []);
    $payee = Payee::factory()->create();
    $changes = $reference === 'payee' ? ['payee_id' => $payee->id] : ['category_id' => $category->id];
    $input = $action === CreateTransaction::class ? manualTransactionInput($changes) : ['expected_revision' => 1, ...$changes];
    if ($action === PostTransaction::class) {
        $input['posted_date'] = '2026-10-02';
    }
    $target = $action === CreateTransaction::class ? $account : $transaction;

    expect(fn () => app($action)->handle($actor, $team, $target, $input))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('account_transactions', 1);
    $this->assertDatabaseCount('transaction_revisions', 1);
})->with([CreateTransaction::class, PostTransaction::class, ClassifyTransaction::class])->with(['category', 'payee', 'kind']);

test('archived references cannot be newly assigned but existing references can be retained', function () {
    [$actor, $team, $account] = transactionLifecycleContext();
    $category = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $payee = Payee::factory()->create(['team_id' => $team->id]);
    $transaction = app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput(['category_id' => $category->id, 'payee_id' => $payee->id]));
    $category->update(['archived_at' => now()]);
    $payee->update(['archived_at' => now()]);

    expect(fn () => app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput(['category_id' => $category->id, 'payee_id' => $payee->id])))->toThrow(ValidationException::class);

    $posted = app(PostTransaction::class)->handle($actor, $team, $transaction, [
        'expected_revision' => 1, 'posted_date' => '2026-10-02', 'category_id' => $category->id, 'payee_id' => $payee->id,
    ]);
    expect($posted->category_id)->toBe($category->id);
    expect($posted->payee_id)->toBe($payee->id);
    expect($posted->revisions()->count())->toBe(2);
});

test('no op classification creates no revision and empty forbidden fields never alter financial values', function () {
    [$actor, $team, $account] = transactionLifecycleContext();
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id]);

    $unchanged = app(ClassifyTransaction::class)->handle($actor, $team, $transaction, [
        'expected_revision' => 1, 'kind' => 'expense', 'amount' => null, 'recorded_at' => null, 'reason' => 'No change',
    ]);

    expect($unchanged->amount)->toBe('-100.00000000');
    expect($unchanged->revisions()->count())->toBe(1);
    expect($unchanged->fresh()->kind)->toBe(TransactionKind::Expense);
});

test('failed audit creation rolls back transaction actions when listeners throw or veto', function (bool $creating, bool $throw) {
    [$actor, $team, $account] = transactionLifecycleContext();
    $transaction = $creating ? null : AccountTransaction::factory()->create(['financial_account_id' => $account->id]);
    $event = 'eloquent.creating: '.TransactionRevision::class;
    Event::listen($event, fn () => $throw ? throw new RuntimeException('Audit unavailable') : false);

    try {
        expect(fn () => $creating
            ? app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput())
            : app(PostTransaction::class)->handle($actor, $team, $transaction, ['expected_revision' => 1, 'posted_date' => '2026-10-02', 'amount' => '-200']))
            ->toThrow($throw ? RuntimeException::class : DomainException::class);
    } finally {
        Event::forget($event);
    }

    $this->assertDatabaseCount('account_transactions', $creating ? 0 : 1);
    $this->assertDatabaseCount('transaction_revisions', $creating ? 0 : 1);
    if (! $creating) {
        expect($transaction->fresh()->amount)->toBe('-100.00000000');
        expect($transaction->fresh()->status)->toBe(TransactionStatus::Pending);
    }
})->with([true, false])->with([true, false]);

test('transaction save veto does not report a successful action', function () {
    [$actor, $team, $account] = transactionLifecycleContext();
    $event = 'eloquent.saving: '.AccountTransaction::class;
    Event::listen($event, fn () => false);

    try {
        expect(fn () => app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput()))->toThrow(DomainException::class);
    } finally {
        Event::forget($event);
    }

    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_revisions', 0);
});

test('outer rollback removes all nested lifecycle mutations and revisions without touching observations', function () {
    [$actor, $team, $account] = transactionLifecycleContext();
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);

    expect(fn () => DB::transaction(function () use ($actor, $team, $account): void {
        $transaction = app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput());
        $transaction = app(PostTransaction::class)->handle($actor, $team, $transaction, ['expected_revision' => 1, 'posted_date' => '2026-10-02']);
        app(ClassifyTransaction::class)->handle($actor, $team, $transaction, ['expected_revision' => 2, 'excluded_from_analytics' => true]);
        throw new RuntimeException('Outer rollback');
    }))->toThrow(RuntimeException::class, 'Outer rollback');

    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_revisions', 0);
    expect($balance->fresh()->amount)->toBe('100.00000000');
});

test('a caught nested audit failure leaves the original transaction available for a later accepted change', function () {
    [$actor, $team, $account] = transactionLifecycleContext();

    $transaction = DB::transaction(function () use ($actor, $team, $account): AccountTransaction {
        $transaction = app(CreateTransaction::class)->handle($actor, $team, $account, manualTransactionInput());
        $event = 'eloquent.creating: '.TransactionRevision::class;
        Event::listen($event, fn () => false);
        try {
            app(PostTransaction::class)->handle($actor, $team, $transaction, ['expected_revision' => 1, 'posted_date' => '2026-10-02']);
            test()->fail('Expected audit failure.');
        } catch (DomainException $exception) {
            expect($exception->getMessage())->toBe('The transaction revision could not be recorded.');
        } finally {
            Event::forget($event);
        }

        return app(ClassifyTransaction::class)->handle($actor, $team, $transaction, ['expected_revision' => 1, 'excluded_from_analytics' => true]);
    });

    expect($transaction->fresh()->status)->toBe(TransactionStatus::Pending);
    expect($transaction->fresh()->posted_date)->toBeNull();
    expect($transaction->fresh()->excluded_from_analytics)->toBeTrue();
    expect($transaction->fresh()->revisions()->get()->pluck('revision_number')->all())->toBe([1, 2]);
    expect($transaction->fresh()->revisions()->get()->last()->snapshot)->toBe($transaction->fresh()->canonicalSnapshot());
});
