<?php

use App\Actions\Finance\ClassifyTransaction;
use App\Actions\Finance\ReverseTransaction;
use App\Enums\FinancialAccountType;
use App\Enums\TeamRole;
use App\Enums\TransactionKind;
use App\Enums\TransactionStatus;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Payee;
use App\Models\Team;
use App\Models\TransactionCategory;
use App\Models\TransactionRevision;
use App\Models\TransactionTransfer;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{User, Team, FinancialAccount, AccountTransaction} */
function reversalContext(array $accountAttributes = [], array $transactionAttributes = []): array
{
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $account = FinancialAccount::factory()->create(['team_id' => $team->id, ...$accountAttributes]);
    $transaction = AccountTransaction::factory()->posted()->create([
        'financial_account_id' => $account->id, 'created_by' => $actor->id,
        'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-02', ...$transactionAttributes,
    ]);

    return [$actor, $team, $account, $transaction];
}

/** @return array<string, mixed> */
function reversalInput(array $changes = []): array
{
    return array_replace(['expected_revision' => 1, 'posted_date' => '2026-10-03', 'reason' => 'Incorrect recorded activity'], $changes);
}

test('standalone reversal exactly offsets native movement while retaining the original and one canonical audit', function (FinancialAccountType $type, TransactionKind $kind, string $originalAmount, string $reversedAmount, string $currency) {
    $this->freezeTime();
    [$actor, $team, $account, $original] = reversalContext(['type' => $type, 'currency_code' => $currency], ['amount' => $originalAmount, 'kind' => $kind]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $balanceBefore = $balance->fresh()->getAttributes();
    $originalBefore = $original->fresh()->getAttributes();
    $auditBefore = $original->revisions()->sole()->toArray();
    $this->travel(1)->seconds();

    $reversal = app(ReverseTransaction::class)->handle($actor, $team, $original, reversalInput());

    expect($reversal->fresh()->amount)->toBe($reversedAmount);
    expect((string) BigDecimal::of($original->amount)->plus($reversal->amount))->toBe('0.00000000');
    expect($reversal->financial_account_id)->toBe($account->id);
    expect($reversal->currency_code)->toBe($currency);
    expect($reversal->kind)->toBe($kind);
    expect($reversal->status)->toBe(TransactionStatus::Posted);
    expect($reversal->transaction_date->toDateString())->toBe('2026-10-03');
    expect($reversal->posted_date->toDateString())->toBe('2026-10-03');
    expect($reversal->recorded_at->greaterThan($original->recorded_at))->toBeTrue();
    expect($reversal->reversesTransaction->is($original))->toBeTrue();
    expect($original->fresh()->reversal->is($reversal))->toBeTrue();
    expect($original->fresh()->getAttributes())->toBe($originalBefore);
    expect($original->revisions()->sole()->toArray())->toBe($auditBefore);
    expect($balance->fresh()->getAttributes())->toBe($balanceBefore);
    $revision = $reversal->revisions()->sole();
    expect($revision->revision_number)->toBe(1);
    expect($revision->operation->value)->toBe('created');
    expect($revision->actor_id)->toBe($actor->id);
    expect($revision->reason)->toBe('Incorrect recorded activity');
    expect($revision->snapshot)->toBe($reversal->fresh()->canonicalSnapshot());
    expect(json_decode($reversal->toJson(), true)['amount'])->toBe($reversedAmount);
    $this->assertDatabaseCount('account_transactions', 2);
    $this->assertDatabaseCount('transaction_revisions', 2);
})->with([
    'asset expense' => [FinancialAccountType::Checking, TransactionKind::Expense, '-100.00000000', '100.00000000', 'USD'],
    'asset income' => [FinancialAccountType::Savings, TransactionKind::Income, '1000.00000000', '-1000.00000000', 'USD'],
    'liability expense' => [FinancialAccountType::CreditCard, TransactionKind::Expense, '100.00000000', '-100.00000000', 'USD'],
    'liability refund income' => [FinancialAccountType::CreditCard, TransactionKind::Income, '-10.00000000', '10.00000000', 'USD'],
    'INR precision' => [FinancialAccountType::Checking, TransactionKind::Adjustment, '-9999999999999999.12345678', '9999999999999999.12345678', 'INR'],
    'positive limit' => [FinancialAccountType::Loan, TransactionKind::Unclassified, '9999999999999999.99999999', '-9999999999999999.99999999', 'INR'],
    'zero movement' => [FinancialAccountType::Checking, TransactionKind::Adjustment, '0.00000000', '0.00000000', 'USD'],
]);

test('reversals retain archived classification references payee description and analytics semantics', function (bool $excluded) {
    [$actor, $team, $account, $original] = reversalContext();
    $category = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $payee = Payee::factory()->create(['team_id' => $team->id]);
    $original->withRevisionContext($actor)->update(['category_id' => $category->id, 'payee_id' => $payee->id, 'excluded_from_analytics' => $excluded]);
    $category->update(['archived_at' => now()]);
    $payee->update(['archived_at' => now()]);

    $reversal = app(ReverseTransaction::class)->handle($actor, $team, $original, reversalInput(['expected_revision' => 2]));

    expect($reversal->category_id)->toBe($category->id);
    expect($reversal->payee_id)->toBe($payee->id);
    expect($reversal->description)->toBe($original->description);
    expect($reversal->excluded_from_analytics)->toBe($excluded);
    expect($reversal->kind)->toBe($original->kind);
    expect($original->fresh()->revisions()->count())->toBe(2);
    expect($reversal->revisions()->count())->toBe(1);
})->with([false, true]);

test('owners and admins reverse through the scoped endpoint and can follow both correction links', function (TeamRole $role) {
    [$owner, $team, $account, $original] = reversalContext();
    $actor = User::factory()->create();
    $team->members()->attach($actor, ['role' => $role->value]);
    $params = ['current_team' => $team->slug, 'account_transaction' => $original->id];
    $this->actingAs($actor)->get(route('transactions.show', $params))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canReverse', true)->where('reversalId', null));

    $this->post(route('transactions.reverse', $params), reversalInput())->assertSessionHasNoErrors()->assertRedirect();
    $reversal = $original->fresh()->reversal;
    expect($reversal->created_by)->toBe($actor->id);
    expect($reversal->revisions()->sole()->actor_id)->toBe($actor->id);
    $this->get(route('transactions.show', $params))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('canManage', false)->where('canReverse', false)->where('reversalId', $reversal->id)->where('transaction.expected_revision', 1));
    $this->get(route('transactions.show', ['current_team' => $team->slug, 'account_transaction' => $reversal->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false)->where('canReverse', false)
        ->where('transaction.reverses_transaction_id', $original->id)->where('revisions.total', 1)
        ->where('revisions.data.0.reason', 'Incorrect recorded activity')->where('transaction.amount', '100.00000000'));
})->with([TeamRole::Owner, TeamRole::Admin]);

test('members and outsiders cannot reverse while members retain read access', function (bool $member) {
    [$owner, $team, $account, $original] = reversalContext();
    $actor = User::factory()->create();
    if ($member) {
        $team->members()->attach($actor, ['role' => TeamRole::Member->value]);
    }
    expect(Gate::forUser($actor)->allows('reverse', $original))->toBeFalse();
    expect(fn () => app(ReverseTransaction::class)->handle($actor, $team, $original, reversalInput()))->toThrow(AuthorizationException::class);
    $params = ['current_team' => $team->slug, 'account_transaction' => $original->id];
    $this->actingAs($actor)->post(route('transactions.reverse', $params), reversalInput())->assertForbidden();
    if ($member) {
        $this->get(route('transactions.show', $params))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canReverse', false));
    }
    $this->assertDatabaseCount('account_transactions', 1);
    $this->assertDatabaseCount('transaction_revisions', 1);
})->with([true, false]);

test('guests cannot access the reversal endpoint', function () {
    [$owner, $team, $account, $original] = reversalContext();
    $this->post(route('transactions.reverse', ['current_team' => $team->slug, 'account_transaction' => $original->id]), reversalInput())
        ->assertRedirect(route('login'));
});

test('explicit route team rejects foreign transaction ids even for users belonging to both households', function () {
    [$actor, $team, $account, $original] = reversalContext();
    $other = Team::factory()->create();
    $other->members()->attach($actor, ['role' => TeamRole::Owner->value]);
    $foreign = AccountTransaction::factory()->posted()->create(['financial_account_id' => FinancialAccount::factory()->create(['team_id' => $other->id])->id]);
    $actor->switchTeam($other);

    $this->actingAs($actor)->postJson(route('transactions.reverse', ['current_team' => $team->slug, 'account_transaction' => $foreign->id]), reversalInput())->assertNotFound();
    expect(fn () => app(ReverseTransaction::class)->handle($actor, $team, $foreign, reversalInput()))->toThrow(ModelNotFoundException::class);
    $this->post(route('transactions.reverse', ['current_team' => $team->slug, 'account_transaction' => $original->id]), reversalInput())->assertSessionHasNoErrors();
    expect($original->fresh()->reversal->team_id)->toBe($team->id);
    expect($foreign->fresh()->reversal)->toBeNull();
});

test('invalid reversal inputs cannot alter financial fields or create audits', function (array $changes, string $field) {
    [$actor, $team, $account, $original] = reversalContext();
    $this->actingAs($actor)->postJson(route('transactions.reverse', ['current_team' => $team->slug, 'account_transaction' => $original->id]), reversalInput($changes))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('account_transactions', 1);
    $this->assertDatabaseCount('transaction_revisions', 1);
})->with([
    'missing reason' => [['reason' => null], 'reason'],
    'blank reason' => [['reason' => '   '], 'reason'],
    'long reason' => [['reason' => str_repeat('a', 2001)], 'reason'],
    'missing date' => [['posted_date' => null], 'posted_date'],
    'invalid date' => [['posted_date' => '2026-02-30'], 'posted_date'],
    'earlier date' => [['posted_date' => '2026-10-01'], 'posted_date'],
    'missing revision' => [['expected_revision' => null], 'expected_revision'],
    'invalid revision' => [['expected_revision' => 0], 'expected_revision'],
    'amount injection' => [['amount' => '50'], 'amount'],
    'team injection' => [['team_id' => 100], 'team_id'],
    'account injection' => [['financial_account_id' => 100], 'financial_account_id'],
    'category injection' => [['category_id' => 100], 'category_id'],
    'currency injection' => [['currency_code' => 'INR'], 'currency_code'],
    'classification injection' => [['kind' => 'income'], 'kind'],
    'analytics injection' => [['excluded_from_analytics' => true], 'excluded_from_analytics'],
    'correction injection' => [['reverses_transaction_id' => 100], 'reverses_transaction_id'],
]);

test('pending and cancelled activity cannot be reversed', function (TransactionStatus $status) {
    [$actor, $team, $account, $original] = reversalContext([], ['status' => $status, 'posted_date' => null]);
    $this->actingAs($actor)->postJson(route('transactions.reverse', ['current_team' => $team->slug, 'account_transaction' => $original->id]), reversalInput())
        ->assertUnprocessable()->assertJsonPath('errors.status.0', 'Only posted transactions can be reversed.');
    $this->assertDatabaseCount('account_transactions', 1);
})->with([TransactionStatus::Pending, TransactionStatus::Cancelled]);

test('archived accounts reject reversals without losing history', function () {
    [$actor, $team, $account, $original] = reversalContext();
    $account->update(['archived_at' => now()]);
    $this->actingAs($actor)->postJson(route('transactions.reverse', ['current_team' => $team->slug, 'account_transaction' => $original->id]), reversalInput())->assertForbidden();
    expect(fn () => app(ReverseTransaction::class)->handle($actor, $team, $original, reversalInput()))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('transaction_revisions', 1);
});

test('stale classification revisions are rejected before reversal and drafts are not retried', function () {
    [$actor, $team, $account, $original] = reversalContext();
    app(ClassifyTransaction::class)->handle($actor, $team, $original, ['expected_revision' => 1, 'description' => 'Accepted elsewhere']);
    $this->actingAs($actor)->postJson(route('transactions.reverse', ['current_team' => $team->slug, 'account_transaction' => $original->id]), reversalInput())
        ->assertUnprocessable()->assertJsonPath('errors.expected_revision.0', 'This transaction has changed. Reload it before trying again.');
    expect($original->fresh()->reversal)->toBeNull();
    $this->assertDatabaseCount('account_transactions', 1);
    $this->assertDatabaseCount('transaction_revisions', 2);
});

test('repeat and racing requests using unchanged original tokens create only one reversal', function () {
    [$actor, $team, $account, $original] = reversalContext();
    $staleCopy = $original->fresh();
    $reversal = app(ReverseTransaction::class)->handle($actor, $team, $original, reversalInput());
    expect(fn () => app(ReverseTransaction::class)->handle($actor, $team, $staleCopy, reversalInput()))
        ->toThrow(ValidationException::class, 'This transaction has already been reversed.');
    $this->actingAs($actor)->postJson(route('transactions.reverse', ['current_team' => $team->slug, 'account_transaction' => $original->id]), reversalInput())
        ->assertUnprocessable()->assertJsonValidationErrors('reversal');
    $this->postJson(route('transactions.reverse', ['current_team' => $team->slug, 'account_transaction' => $reversal->id]), reversalInput())
        ->assertUnprocessable()->assertJsonValidationErrors('reversal');
    $this->assertDatabaseCount('account_transactions', 2);
    $this->assertDatabaseCount('transaction_revisions', 2);
});

test('reversal pairs cannot drift through classification actions endpoints or quiet model saves', function (bool $reverseSide) {
    [$actor, $team, $account, $original] = reversalContext();
    $reversal = app(ReverseTransaction::class)->handle($actor, $team, $original, reversalInput());
    $target = $reverseSide ? $reversal : $original;
    expect(fn () => app(ClassifyTransaction::class)->handle($actor, $team, $target, ['expected_revision' => 1, 'kind' => 'income']))->toThrow(AuthorizationException::class);
    $this->actingAs($actor)->patchJson(route('transactions.classify', ['current_team' => $team->slug, 'account_transaction' => $target->id]), ['expected_revision' => 1, 'excluded_from_analytics' => true])->assertForbidden();
    $target->description = 'Attempted rewrite';
    expect(fn () => $target->saveQuietly())->toThrow(DomainException::class);
    $this->assertDatabaseCount('transaction_revisions', 2);
})->with([false, true]);

test('both grouped transfer legs are excluded from standalone reversal', function () {
    $transfer = TransactionTransfer::factory()->create();
    $actor = User::factory()->create();
    $team = $transfer->team;
    $team->members()->attach($actor, ['role' => TeamRole::Owner->value]);
    foreach ([$transfer->sourceTransaction, $transfer->destinationTransaction] as $leg) {
        $this->actingAs($actor)->postJson(route('transactions.reverse', ['current_team' => $team->slug, 'account_transaction' => $leg->id]), reversalInput())->assertForbidden();
        expect(fn () => app(ReverseTransaction::class)->handle($actor, $team, $leg, reversalInput()))->toThrow(AuthorizationException::class);
    }
    $this->assertDatabaseCount('account_transactions', 2);
    $this->assertDatabaseCount('transaction_revisions', 2);
});

test('reserved transfer movements and existing replacement links cannot enter the reversal workflow', function (string $state) {
    [$actor, $team, $account, $original] = reversalContext([], $state === 'transfer' ? ['kind' => TransactionKind::Transfer] : []);
    $target = $original;
    if ($state !== 'transfer') {
        $replacement = AccountTransaction::factory()->posted()->create([
            'financial_account_id' => $account->id, 'replaces_transaction_id' => $original->id,
            'transaction_date' => '2026-10-02', 'posted_date' => '2026-10-02',
        ]);
        $target = $state === 'replacement' ? $replacement : $original;
    }
    $response = $this->actingAs($actor)->postJson(route('transactions.reverse', ['current_team' => $team->slug, 'account_transaction' => $target->id]), reversalInput());
    if ($state === 'transfer') {
        $response->assertForbidden();
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('reversal');
    }
    expect($target->fresh()->reversal)->toBeNull();
    $this->assertDatabaseCount('account_transactions', $state === 'transfer' ? 1 : 2);
})->with(['transfer', 'replacement', 'replaced original']);

test('reversal persistence failures roll back both movement and audit atomically', function (string $failure) {
    [$actor, $team, $account, $original] = reversalContext();
    $event = 'eloquent.creating: '.($failure === 'movement' ? AccountTransaction::class : TransactionRevision::class);
    Event::listen($event, function () use ($failure): ?bool {
        if ($failure === 'audit veto') {
            return false;
        }
        throw new RuntimeException('Injected reversal failure');
    });
    try {
        expect(fn () => app(ReverseTransaction::class)->handle($actor, $team, $original, reversalInput()))
            ->toThrow($failure === 'audit veto' ? DomainException::class : RuntimeException::class);
    } finally {
        Event::forget($event);
    }
    expect($original->fresh()->reversal)->toBeNull();
    $this->assertDatabaseCount('account_transactions', 1);
    $this->assertDatabaseCount('transaction_revisions', 1);
})->with(['movement', 'audit exception', 'audit veto']);

test('outer transaction rollback removes a successful reversal and its creation audit', function () {
    [$actor, $team, $account, $original] = reversalContext();
    expect(fn () => DB::transaction(function () use ($actor, $team, $original): void {
        app(ReverseTransaction::class)->handle($actor, $team, $original, reversalInput());
        throw new RuntimeException('Caller rollback');
    }))->toThrow(RuntimeException::class, 'Caller rollback');
    $this->assertDatabaseCount('account_transactions', 1);
    $this->assertDatabaseCount('transaction_revisions', 1);
    expect($original->fresh()->reversal)->toBeNull();
});
