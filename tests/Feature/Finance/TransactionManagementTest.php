<?php

use App\Enums\FinancialAccountType;
use App\Enums\TeamRole;
use App\Enums\TransactionCategoryKind;
use App\Enums\TransactionKind;
use App\Enums\TransactionStatus;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Payee;
use App\Models\Team;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{User, Team, FinancialAccount} */
function transactionHttpContext(): array
{
    $actor = User::factory()->create();
    $team = $actor->personalTeam();

    return [$actor, $team, FinancialAccount::factory()->create(['team_id' => $team->id])];
}

/** @param array<string, mixed> $changes
 * @return array<string, mixed>
 */
function transactionHttpPayload(FinancialAccount $account, array $changes = []): array
{
    return array_replace([
        'financial_account_id' => $account->id, 'kind' => 'expense', 'amount' => '-10.12345678',
        'transaction_date' => '2026-10-01', 'status' => 'pending', 'description' => 'Manual expense',
    ], $changes);
}

test('transaction listing paginates with deterministic date recording time and id ordering', function () {
    [$actor, $team, $account] = transactionHttpContext();
    $records = AccountTransaction::factory()->count(31)->create([
        'financial_account_id' => $account->id, 'transaction_date' => '2026-10-01', 'recorded_at' => '2026-10-02 12:00:00.123456',
    ]);
    AccountTransaction::factory()->create();

    $this->actingAs($actor)->get(route('transactions.index', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('transactions/index')
        ->where('team.id', $team->id)->where('canCreate', true)
        ->where('transactions.total', 31)->has('transactions.data', 25)
        ->where('transactions.data.0.id', $records->last()->id)
        ->where('transactions.data.24.id', $records[6]->id)
        ->has('accounts', 1));
    $this->get(route('transactions.index', ['current_team' => $team->slug, 'page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('transactions.data', 6)
        ->where('transactions.current_page', 2)->where('transactions.data.0.id', $records[5]->id));
});

test('transaction filters combine within the route household and use inclusive economic dates', function (string $filter) {
    [$actor, $team, $account] = transactionHttpContext();
    $category = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $match = AccountTransaction::factory()->posted()->create([
        'financial_account_id' => $account->id, 'category_id' => $category->id, 'transaction_date' => '2026-10-01', 'posted_date' => '2026-10-03',
    ]);
    $second = AccountTransaction::factory()->create([
        'financial_account_id' => FinancialAccount::factory()->create(['team_id' => $team->id])->id,
        'kind' => TransactionKind::Income, 'transaction_date' => '2026-10-02',
    ]);
    $third = AccountTransaction::factory()->create(['financial_account_id' => $account->id, 'kind' => TransactionKind::Adjustment, 'transaction_date' => '2026-09-30']);
    [$filters, $expected] = match ($filter) {
        'account' => [['financial_account_id' => $account->id], [$match->id, $third->id]],
        'status' => [['status' => 'posted'], [$match->id]],
        'kind' => [['kind' => 'expense'], [$match->id]],
        'category' => [['category_id' => $category->id], [$match->id]],
        'from' => [['date_from' => '2026-10-01'], [$second->id, $match->id]],
        'to' => [['date_to' => '2026-10-01'], [$match->id, $third->id]],
        'range' => [['date_from' => '2026-10-01', 'date_to' => '2026-10-01'], [$match->id]],
        'combined' => [['financial_account_id' => $account->id, 'status' => 'posted', 'kind' => 'expense', 'category_id' => $category->id, 'date_from' => '2026-10-01', 'date_to' => '2026-10-01'], [$match->id]],
    };

    $this->actingAs($actor)->get(route('transactions.index', ['current_team' => $team->slug, ...$filters]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('transactions.total', count($expected))
        ->where('transactions.data', fn ($rows) => collect($rows)->pluck('id')->all() === $expected));
})->with(['account', 'status', 'kind', 'category', 'from', 'to', 'range', 'combined']);

test('empty households render transaction list and creation empty states', function () {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();

    $this->actingAs($actor)->get(route('transactions.index', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('transactions.data', 0)->has('accounts', 0));
    $this->get(route('transactions.create', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('transactions/create')->has('accounts', 0)->has('categories', 0)->has('payees', 0));
});

test('detail reads pair accepted financial fields with the same revision token even when route binding is stale', function () {
    [$actor, $team, $account] = transactionHttpContext();
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id]);
    $staleBinding = $transaction->fresh();
    $transaction->update(['amount' => '-200.12345678']);
    Route::bind('account_transaction', fn () => $staleBinding);

    $this->actingAs($actor)->get(route('transactions.show', ['current_team' => $team->slug, 'account_transaction' => $transaction->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('transaction.amount', '-200.12345678')->where('transaction.expected_revision', 2)
        ->where('revisions.data.0.snapshot.amount', '-200.12345678'));
});

test('owners and admins create pending and posted manual transactions through the selected team', function (TeamRole $role, string $status) {
    [$owner, $team, $account] = transactionHttpContext();
    $actor = User::factory()->create();
    $team->members()->attach($actor, ['role' => $role->value]);
    $account->update(['type' => FinancialAccountType::CreditCard, 'currency_code' => 'INR']);
    $input = transactionHttpPayload($account, [
        'amount' => '9999999999999999.12345678', 'status' => $status,
        'posted_date' => $status === 'posted' ? '2026-10-02' : null, 'reason' => 'Manual entry',
    ]);

    $this->actingAs($actor)->post(route('transactions.store', ['current_team' => $team->slug]), $input)
        ->assertSessionHasNoErrors()->assertRedirect();
    $transaction = AccountTransaction::sole();
    expect($transaction->status->value)->toBe($status);
    expect($transaction->created_by)->toBe($actor->id);
    expect($transaction->revisions()->sole()->actor_id)->toBe($actor->id);
    expect($transaction->revisions()->sole()->reason)->toBe('Manual entry');
    $this->get(route('transactions.show', ['current_team' => $team->slug, 'account_transaction' => $transaction->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('transactions/show')
        ->where('transaction.amount', '9999999999999999.12345678')->where('transaction.currency_code', 'INR')
        ->where('transaction.account.isLiability', true)->where('transaction.kind', 'expense')
        ->where('transaction.expected_revision', 1)->has('revisions.data', 1)
        ->where('revisions.data.0.snapshot.amount', '9999999999999999.12345678'));
})->with([TeamRole::Owner, TeamRole::Admin])->with(['pending', 'posted']);

test('posting classification and cancellation reuse actions and preserve balance observations', function () {
    [$actor, $team, $account] = transactionHttpContext();
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id]);
    $before = $balance->fresh()->getRawOriginal();
    $category = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $payee = Payee::factory()->create(['team_id' => $team->id]);
    $params = ['current_team' => $team->slug];

    $this->actingAs($actor)->post(route('transactions.store', $params), transactionHttpPayload($account))->assertSessionHasNoErrors();
    $transaction = AccountTransaction::sole();
    $params['account_transaction'] = $transaction->id;
    $recordedAt = $transaction->recorded_at->toISOString();
    $this->post(route('transactions.post', $params), ['expected_revision' => 1, 'posted_date' => '2026-10-02', 'amount' => '-12.12345678'])
        ->assertSessionHasNoErrors()->assertRedirectToRoute('transactions.show', $params);
    $this->patch(route('transactions.classify', $params), [
        'expected_revision' => 2, 'category_id' => $category->id, 'payee_id' => $payee->id, 'excluded_from_analytics' => true, 'reason' => 'Reviewed',
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($transaction->fresh()->status)->toBe(TransactionStatus::Posted);
    expect($transaction->fresh()->amount)->toBe('-12.12345678');
    expect($transaction->fresh()->recorded_at->toISOString())->toBe($recordedAt);
    expect($transaction->fresh()->category_id)->toBe($category->id);
    expect($transaction->fresh()->payee_id)->toBe($payee->id);
    expect($transaction->fresh()->revisions()->get()->pluck('revision_number')->all())->toBe([1, 2, 3]);

    $this->post(route('transactions.store', ['current_team' => $team->slug]), transactionHttpPayload($account))->assertSessionHasNoErrors();
    $pending = AccountTransaction::where('status', 'pending')->sole();
    $this->post(route('transactions.cancel', ['current_team' => $team->slug, 'account_transaction' => $pending->id]), ['expected_revision' => 1, 'reason' => 'Released'])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($pending->fresh()->status)->toBe(TransactionStatus::Cancelled);
    expect($pending->fresh()->revisions()->get()->last()->reason)->toBe('Released');
    expect($balance->fresh()->getRawOriginal())->toBe($before);
    $this->assertDatabaseCount('account_balances', 1);
    $this->assertDatabaseCount('transaction_revisions', 5);
});

test('members can view paginated audit history but cannot mutate transactions', function () {
    [$owner, $team, $account] = transactionHttpContext();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id]);
    for ($revision = 2; $revision <= 27; $revision++) {
        $transaction->withRevisionContext($owner, 'Reviewed')->update(['description' => 'Revision '.$revision]);
    }
    $params = ['current_team' => $team->slug, 'account_transaction' => $transaction->id];

    $this->actingAs($member)->get(route('transactions.show', $params))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false)
        ->where('transaction.expected_revision', 27)->where('revisions.total', 27)->has('revisions.data', 25)
        ->where('revisions.data.0.revision_number', 27)->where('revisions.data.0.actor.name', $owner->name));
    $this->get(route('transactions.show', [...$params, 'revisions_page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('revisions.data', 2)->where('revisions.data.0.revision_number', 2));
    $this->get(route('transactions.create', ['current_team' => $team->slug]))->assertForbidden();
    $this->post(route('transactions.store', ['current_team' => $team->slug]), transactionHttpPayload($account))->assertForbidden();
    $this->post(route('transactions.post', $params), [])->assertForbidden();
    $this->post(route('transactions.cancel', $params), [])->assertForbidden();
    $this->patch(route('transactions.classify', $params), [])->assertForbidden();
    expect($transaction->fresh()->revisions()->count())->toBe(27);
});

test('cross team transaction bindings hide foreign records from every route', function (string $name, string $method) {
    [$actor, $team] = transactionHttpContext();
    $foreign = AccountTransaction::factory()->create();

    $this->actingAs($actor)->json($method, route($name, ['current_team' => $team->slug, 'account_transaction' => $foreign->id]), [])
        ->assertNotFound();
    expect($foreign->fresh()->revisions()->count())->toBe(1);
})->with([
    ['transactions.show', 'GET'], ['transactions.post', 'POST'], ['transactions.cancel', 'POST'], ['transactions.classify', 'PATCH'],
]);

test('outsiders cannot access another household and guests must authenticate', function () {
    [$actor, $team] = transactionHttpContext();
    $this->get(route('transactions.index', ['current_team' => $team->slug]))->assertRedirectToRoute('login');
    $this->actingAs(User::factory()->create())->get(route('transactions.index', ['current_team' => $team->slug]))->assertForbidden();
});

test('invalid team references and category classifications are rejected without audit side effects', function (string $field) {
    [$actor, $team, $account] = transactionHttpContext();
    $foreignAccount = FinancialAccount::factory()->create();
    $foreignCategory = TransactionCategory::factory()->create();
    $foreignPayee = Payee::factory()->create();
    $income = TransactionCategory::factory()->create(['team_id' => $team->id, 'kind' => TransactionCategoryKind::Income]);
    $input = transactionHttpPayload($account, match ($field) {
        'account' => ['financial_account_id' => $foreignAccount->id],
        'category' => ['category_id' => $foreignCategory->id],
        'payee' => ['payee_id' => $foreignPayee->id],
        'kind' => ['category_id' => $income->id],
        'amount' => ['amount' => 1.5],
        'posting' => ['status' => 'posted'],
        'currency' => ['currency_code' => 'INR'],
    });

    $this->actingAs($actor)->postJson(route('transactions.store', ['current_team' => $team->slug]), $input)->assertUnprocessable();
    $this->assertDatabaseCount('account_transactions', 0);
    $this->assertDatabaseCount('transaction_revisions', 0);
})->with(['account', 'category', 'payee', 'kind', 'amount', 'posting', 'currency']);

test('invalid filters cannot reference other households or reverse the date range', function (string $filter) {
    [$actor, $team] = transactionHttpContext();
    $foreign = FinancialAccount::factory()->create();
    $category = TransactionCategory::factory()->create();
    $input = match ($filter) {
        'account' => ['financial_account_id' => $foreign->id],
        'category' => ['category_id' => $category->id],
        'date' => ['date_from' => '2026-10-02', 'date_to' => '2026-10-01'],
    };

    $this->actingAs($actor)->getJson(route('transactions.index', ['current_team' => $team->slug, ...$input]))->assertUnprocessable();
})->with(['account', 'category', 'date']);

test('archived accounts retain readable history while every mutation is rejected', function () {
    [$actor, $team, $account] = transactionHttpContext();
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id]);
    $account->update(['archived_at' => now()]);
    $params = ['current_team' => $team->slug, 'account_transaction' => $transaction->id];

    $this->actingAs($actor)->get(route('transactions.show', $params))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false)->where('transaction.account.archived_at', $account->fresh()->archived_at->toISOString()));
    $this->postJson(route('transactions.store', ['current_team' => $team->slug]), transactionHttpPayload($account))->assertUnprocessable();
    $this->post(route('transactions.post', $params), [])->assertForbidden();
    $this->post(route('transactions.cancel', $params), [])->assertForbidden();
    $this->patch(route('transactions.classify', $params), [])->assertForbidden();
    expect($transaction->fresh()->revisions()->count())->toBe(1);
});

test('stale revision conflicts return clear validation feedback without changing accepted state', function (string $name, string $method) {
    [$actor, $team, $account] = transactionHttpContext();
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id]);
    $transaction->update(['description' => 'Accepted concurrent change']);
    $params = ['current_team' => $team->slug, 'account_transaction' => $transaction->id];
    $input = ['expected_revision' => 1, ...($name === 'transactions.post' ? ['posted_date' => '2026-10-02'] : [])];

    $this->actingAs($actor)->from(route('transactions.show', $params))->json($method, route($name, $params), $input)
        ->assertUnprocessable()->assertJsonValidationErrors('expected_revision')
        ->assertJsonPath('errors.expected_revision.0', 'This transaction has changed. Reload it before trying again.');
    expect($transaction->fresh()->description)->toBe('Accepted concurrent change');
    expect($transaction->fresh()->status)->toBe(TransactionStatus::Pending);
    expect($transaction->fresh()->revisions()->count())->toBe(2);
})->with([['transactions.post', 'POST'], ['transactions.cancel', 'POST'], ['transactions.classify', 'PATCH']]);

test('manual routes reserve transfers and classification cannot overwrite posted financial values', function () {
    [$actor, $team, $account] = transactionHttpContext();
    $transfer = AccountTransaction::factory()->create(['financial_account_id' => $account->id, 'kind' => TransactionKind::Transfer]);
    $params = ['current_team' => $team->slug, 'account_transaction' => $transfer->id];

    $this->actingAs($actor)->postJson(route('transactions.store', ['current_team' => $team->slug]), transactionHttpPayload($account, ['kind' => 'transfer']))->assertUnprocessable();
    $this->get(route('transactions.show', $params))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    $this->post(route('transactions.post', $params), [])->assertForbidden();
    $this->post(route('transactions.cancel', $params), [])->assertForbidden();
    $this->patch(route('transactions.classify', $params), [])->assertForbidden();

    $posted = AccountTransaction::factory()->posted()->create(['financial_account_id' => $account->id]);
    $params['account_transaction'] = $posted->id;
    $this->patchJson(route('transactions.classify', $params), ['expected_revision' => 1, 'kind' => 'transfer'])->assertUnprocessable();
    $this->patchJson(route('transactions.classify', $params), ['expected_revision' => 1, 'amount' => '0'])->assertUnprocessable();
    $this->postJson(route('transactions.cancel', $params), ['expected_revision' => 1])->assertUnprocessable();
    expect($posted->fresh()->amount)->toBe('-100.00000000');
    expect($posted->fresh()->revisions()->count())->toBe(1);
});
