<?php

use App\Actions\Finance\SavePayee;
use App\Actions\Finance\SaveTransactionCategory;
use App\Enums\TeamRole;
use App\Enums\TransactionCategoryKind;
use App\Enums\TransactionStatus;
use App\Models\AccountBalance;
use App\Models\AccountTransaction;
use App\Models\FinancialAccount;
use App\Models\Payee;
use App\Models\Team;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{User, Team} */
function referenceContext(): array
{
    $actor = User::factory()->create();

    return [$actor, $actor->personalTeam()];
}

test('category lists search names and parents and preserve household hierarchy', function () {
    [$actor, $team] = referenceContext();
    $root = TransactionCategory::factory()->create(['team_id' => $team->id, 'name' => 'Food']);
    $child = TransactionCategory::factory()->create(['team_id' => $team->id, 'name' => 'Groceries', 'parent_id' => $root->id]);
    TransactionCategory::factory()->create(['team_id' => $team->id, 'name' => 'Salary', 'kind' => TransactionCategoryKind::Income]);
    TransactionCategory::factory()->create(['team_id' => $team->id, 'name' => 'Old', 'archived_at' => now()]);
    TransactionCategory::factory()->create(['name' => 'Food in another household']);

    $this->actingAs($actor)->get(route('categories.index', ['current_team' => $team->slug, 'search' => 'Food']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('categories/index')
        ->where('canManage', true)->where('records.total', 2)->where('records.data.0.id', $root->id)
        ->where('records.data.1.id', $child->id)->where('records.data.1.parent.name', 'Food')
        ->where('roots.0.parent_id', null));
    $this->get(route('categories.index', ['current_team' => $team->slug, 'kind' => 'income']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.name', 'Salary'));
    $this->get(route('categories.index', ['current_team' => $team->slug, 'status' => 'archived']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.name', 'Old'));
});

test('catalog pagination is deterministic with duplicate names', function (string $entity) {
    [$actor, $team] = referenceContext();
    $model = $entity === 'categories' ? TransactionCategory::class : Payee::class;
    $rows = $model::factory()->count(26)->create(['team_id' => $team->id, 'name' => 'Same name']);
    $model::factory()->create(['name' => 'Foreign record']);

    $this->actingAs($actor)->get(route($entity.'.index', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('records.total', 26)->has('records.data', 25)
        ->where('records.data.0.id', $rows[0]->id)->where('records.data.24.id', $rows[24]->id));
    $this->get(route($entity.'.index', ['current_team' => $team->slug, 'page' => 2, 'search' => 'Same']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.id', $rows[25]->id));
})->with(['categories', 'payees']);

test('empty category and payee listings remain readable to household members', function (string $entity) {
    [$owner, $team] = referenceContext();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member)->get(route($entity.'.index', ['current_team' => $team->slug]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component($entity.'/index')
        ->where('canManage', false)->has('records.data', 0));
})->with(['categories', 'payees']);

test('owners and admins create and edit categories and duplicate payees in the route household', function (TeamRole $role) {
    [$owner, $team] = referenceContext();
    $actor = User::factory()->create();
    $team->members()->attach($actor, ['role' => $role->value]);

    $rootId = $this->actingAs($actor)->postJson(route('categories.store', ['current_team' => $team->slug]), [
        'name' => 'Food', 'kind' => 'expense', 'parent_id' => null,
    ])->assertCreated()->assertJsonPath('category.kind', 'expense')->json('category.id');
    $childId = $this->postJson(route('categories.store', ['current_team' => $team->slug]), [
        'name' => 'Groceries', 'kind' => 'expense', 'parent_id' => $rootId,
    ])->assertCreated()->assertJsonPath('category.parent.name', 'Food')->json('category.id');
    $this->put(route('categories.update', ['current_team' => $team->slug, 'transaction_category' => $childId]), [
        'name' => 'Household groceries', 'kind' => 'expense', 'parent_id' => $rootId,
    ])->assertSessionHasNoErrors()->assertRedirectToRoute('categories.index', ['current_team' => $team->slug]);

    $firstId = $this->postJson(route('payees.store', ['current_team' => $team->slug]), ['name' => 'Local market'])->assertCreated()->json('payee.id');
    $secondId = $this->postJson(route('payees.store', ['current_team' => $team->slug]), ['name' => 'Local market'])->assertCreated()->json('payee.id');
    expect($firstId)->not->toBe($secondId);
    $this->put(route('payees.update', ['current_team' => $team->slug, 'payee' => $firstId]), ['name' => 'Neighborhood market'])
        ->assertSessionHasNoErrors()->assertRedirectToRoute('payees.index', ['current_team' => $team->slug]);
    $this->assertDatabaseHas('transaction_categories', ['id' => $childId, 'team_id' => $team->id, 'name' => 'Household groceries', 'parent_id' => $rootId]);
    $this->assertDatabaseHas('payees', ['id' => $firstId, 'team_id' => $team->id, 'name' => 'Neighborhood market']);
    expect(Payee::forTeam($team)->count())->toBe(2);
})->with([TeamRole::Owner, TeamRole::Admin]);

test('category validation rejects cycles extra depth incompatible kinds and moving a parent below another root', function (string $case) {
    [$actor, $team] = referenceContext();
    $root = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $child = TransactionCategory::factory()->create(['team_id' => $team->id, 'parent_id' => $root->id]);
    $otherRoot = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $target = match ($case) {
        'depth' => $otherRoot,
        'kind' => $child,
        default => $root,
    };
    $parentId = match ($case) {
        'self' => $root->id,
        'cycle', 'depth' => $child->id,
        'kind' => $root->id,
        'move parent' => $otherRoot->id,
    };
    $input = ['name' => $target->name, 'kind' => $case === 'kind' ? 'income' : 'expense', 'parent_id' => $parentId];

    $this->actingAs($actor)->putJson(route('categories.update', ['current_team' => $team->slug, 'transaction_category' => $target->id]), $input)
        ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    expect($root->fresh()->parent_id)->toBeNull();
    expect($child->fresh()->parent_id)->toBe($root->id);
})->with(['self', 'cycle', 'depth', 'kind', 'move parent']);

test('an unused category may change kind while transaction dependencies always lock the kind', function (TransactionStatus $status) {
    [$actor, $team] = referenceContext();
    $category = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $unused = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $transaction = AccountTransaction::factory()->create([
        'financial_account_id' => FinancialAccount::factory()->create(['team_id' => $team->id])->id,
        'category_id' => $category->id, 'status' => $status, 'posted_date' => $status === TransactionStatus::Posted ? '2026-10-01' : null,
    ]);
    $before = $transaction->fresh()->getRawOriginal();
    $snapshots = $transaction->revisions()->get()->toArray();

    $this->actingAs($actor)->putJson(route('categories.update', ['current_team' => $team->slug, 'transaction_category' => $category->id]), [
        'name' => $category->name, 'kind' => 'income', 'parent_id' => null,
    ])->assertUnprocessable()->assertJsonValidationErrors('kind');
    $this->put(route('categories.update', ['current_team' => $team->slug, 'transaction_category' => $unused->id]), [
        'name' => 'Salary', 'kind' => 'income', 'parent_id' => null,
    ])->assertSessionHasNoErrors();
    expect($unused->fresh()->kind)->toBe(TransactionCategoryKind::Income);
    expect($category->fresh()->kind)->toBe(TransactionCategoryKind::Expense);
    expect($transaction->fresh()->getRawOriginal())->toBe($before);
    expect($transaction->revisions()->get()->toArray())->toBe($snapshots);
})->with(TransactionStatus::cases());

test('archive operations preserve transactions revisions observations and reference ids', function () {
    [$actor, $team] = referenceContext();
    $category = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $payee = Payee::factory()->create(['team_id' => $team->id]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $balance = AccountBalance::factory()->create(['financial_account_id' => $account->id, 'amount' => '500.12345678']);
    $transaction = AccountTransaction::factory()->posted()->create(['financial_account_id' => $account->id, 'category_id' => $category->id, 'payee_id' => $payee->id]);
    $before = $transaction->fresh()->getRawOriginal();
    $balanceBefore = $balance->fresh()->getRawOriginal();
    $revisionsBefore = $transaction->revisions()->get()->toArray();

    $this->actingAs($actor)->put(route('categories.update', ['current_team' => $team->slug, 'transaction_category' => $category->id]), [
        'name' => 'Renamed category', 'kind' => 'expense', 'parent_id' => null,
    ])->assertSessionHasNoErrors();
    $this->put(route('payees.update', ['current_team' => $team->slug, 'payee' => $payee->id]), ['name' => 'Renamed payee'])->assertSessionHasNoErrors();
    $this->post(route('categories.archive', ['current_team' => $team->slug, 'transaction_category' => $category->id]))->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route('payees.archive', ['current_team' => $team->slug, 'payee' => $payee->id]))->assertSessionHasNoErrors()->assertRedirect();

    expect($category->fresh()->archived_at)->not->toBeNull();
    expect($payee->fresh()->archived_at)->not->toBeNull();
    expect($transaction->fresh()->getRawOriginal())->toBe($before);
    expect($balance->fresh()->getRawOriginal())->toBe($balanceBefore);
    expect($transaction->revisions()->get()->toArray())->toBe($revisionsBefore);
    $this->get(route('payees.index', ['current_team' => $team->slug, 'status' => 'archived', 'search' => 'Renamed']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.id', $payee->id));
    $this->assertDatabaseCount('account_transactions', 1);
    $this->assertDatabaseCount('transaction_revisions', 1);
});

test('parents require active children to be archived first and archived parents reject new subcategories', function () {
    [$actor, $team] = referenceContext();
    $root = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $child = TransactionCategory::factory()->create(['team_id' => $team->id, 'parent_id' => $root->id]);
    $rootParams = ['current_team' => $team->slug, 'transaction_category' => $root->id];

    $this->actingAs($actor)->postJson(route('categories.archive', $rootParams))->assertUnprocessable()->assertJsonValidationErrors('archive');
    expect($root->fresh()->archived_at)->toBeNull();
    $this->post(route('categories.archive', ['current_team' => $team->slug, 'transaction_category' => $child->id]))->assertSessionHasNoErrors();
    $this->putJson(route('categories.update', $rootParams), ['name' => $root->name, 'kind' => 'income', 'parent_id' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('kind');
    $this->post(route('categories.archive', $rootParams))->assertSessionHasNoErrors();
    $archivedAt = $root->fresh()->archived_at->toISOString();
    $this->post(route('categories.archive', $rootParams))->assertSessionHasNoErrors();
    expect($root->fresh()->archived_at->toISOString())->toBe($archivedAt);
    $this->postJson(route('categories.store', ['current_team' => $team->slug]), ['name' => 'New child', 'kind' => 'expense', 'parent_id' => $root->id])
        ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    $this->put(route('categories.update', ['current_team' => $team->slug, 'transaction_category' => $child->id]), [
        'name' => 'Corrected archived name', 'kind' => 'expense', 'parent_id' => $root->id,
    ])->assertSessionHasNoErrors();
    expect($child->fresh()->parent_id)->toBe($root->id);
});

test('archived references cannot be newly assigned during creation posting or classification', function (string $operation, string $entity) {
    [$actor, $team] = referenceContext();
    $category = TransactionCategory::factory()->create(['team_id' => $team->id, 'archived_at' => now()]);
    $payee = Payee::factory()->create(['team_id' => $team->id, 'archived_at' => now()]);
    $account = FinancialAccount::factory()->create(['team_id' => $team->id]);
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => $account->id]);
    $field = $entity === 'category' ? 'category_id' : 'payee_id';
    $value = $entity === 'category' ? $category->id : $payee->id;
    $params = ['current_team' => $team->slug, 'account_transaction' => $transaction->id];

    $this->actingAs($actor);
    $response = match ($operation) {
        'create' => $this->postJson(route('transactions.store', ['current_team' => $team->slug]), [
            'financial_account_id' => $account->id, 'amount' => '-10', 'kind' => 'expense',
            'status' => 'pending', 'transaction_date' => '2026-10-01', $field => $value,
        ]),
        'post' => $this->postJson(route('transactions.post', $params), ['expected_revision' => 1, 'posted_date' => '2026-10-02', $field => $value]),
        'classify' => $this->patchJson(route('transactions.classify', $params), ['expected_revision' => 1, $field => $value]),
    };
    $response->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($transaction->fresh()->category_id)->toBeNull();
    expect($transaction->fresh()->payee_id)->toBeNull();
    $this->assertDatabaseCount('account_transactions', 1);
    $this->assertDatabaseCount('transaction_revisions', 1);
})->with(['create', 'post', 'classify'])->with(['category', 'payee']);

test('inline creation preserves a transaction draft revision and returns immediately selectable household references', function () {
    [$actor, $team] = referenceContext();
    $transaction = AccountTransaction::factory()->create(['financial_account_id' => FinancialAccount::factory()->create(['team_id' => $team->id])->id]);
    $before = $transaction->fresh()->getRawOriginal();
    $categoryId = $this->actingAs($actor)->postJson(route('categories.store', ['current_team' => $team->slug]), ['name' => 'Inline category', 'kind' => 'expense', 'parent_id' => null])
        ->assertCreated()->assertJsonPath('category.parent_id', null)->json('category.id');
    $payeeId = $this->postJson(route('payees.store', ['current_team' => $team->slug]), ['name' => 'Inline payee'])
        ->assertCreated()->json('payee.id');

    expect($transaction->fresh()->getRawOriginal())->toBe($before);
    expect($transaction->revisions()->count())->toBe(1);
    $params = ['current_team' => $team->slug, 'account_transaction' => $transaction->id];
    $this->get(route('transactions.show', $params))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('transaction.expected_revision', 1)->where('categories.0.id', $categoryId)->where('categories.0.parent_id', null)
        ->where('payees.0.id', $payeeId));
    $this->patch(route('transactions.classify', $params), ['expected_revision' => 1, 'category_id' => $categoryId, 'payee_id' => $payeeId])
        ->assertSessionHasNoErrors();
    $this->post(route('categories.archive', ['current_team' => $team->slug, 'transaction_category' => $categoryId]))->assertSessionHasNoErrors();
    $this->post(route('payees.archive', ['current_team' => $team->slug, 'payee' => $payeeId]))->assertSessionHasNoErrors();
    $this->post(route('transactions.post', $params), ['expected_revision' => 2, 'posted_date' => '2026-10-02', 'category_id' => $categoryId, 'payee_id' => $payeeId])
        ->assertSessionHasNoErrors();
    expect($transaction->fresh()->status)->toBe(TransactionStatus::Posted);
    expect($transaction->fresh()->amount)->toBe('-100.00000000');
    expect($transaction->fresh()->revisions()->get()->pluck('revision_number')->all())->toBe([1, 2, 3]);
});

test('member users may search catalogs but cannot create edit or archive records', function (string $entity) {
    [$owner, $team] = referenceContext();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $model = $entity === 'categories' ? TransactionCategory::class : Payee::class;
    $record = $model::factory()->create(['team_id' => $team->id]);
    $param = $entity === 'categories' ? 'transaction_category' : 'payee';

    $this->actingAs($member)->postJson(route($entity.'.store', ['current_team' => $team->slug]), ['name' => 'Denied', 'kind' => 'expense'])->assertForbidden();
    $this->putJson(route($entity.'.update', ['current_team' => $team->slug, $param => $record->id]), ['name' => 'Denied', 'kind' => 'expense'])->assertForbidden();
    $this->postJson(route($entity.'.archive', ['current_team' => $team->slug, $param => $record->id]))->assertForbidden();
    expect($record->fresh()->name)->toBe($record->name);
    expect($record->fresh()->archived_at)->toBeNull();
})->with(['categories', 'payees']);

test('cross team record bindings and parent references are rejected even for users belonging to both households', function (string $entity) {
    [$actor, $team] = referenceContext();
    $other = Team::factory()->create();
    $other->members()->attach($actor, ['role' => TeamRole::Admin->value]);
    $model = $entity === 'categories' ? TransactionCategory::class : Payee::class;
    $record = $model::factory()->create(['team_id' => $other->id]);
    $param = $entity === 'categories' ? 'transaction_category' : 'payee';

    $this->actingAs($actor)->putJson(route($entity.'.update', ['current_team' => $team->slug, $param => $record->id]), ['name' => 'Foreign change', 'kind' => 'expense'])->assertNotFound();
    $this->postJson(route($entity.'.archive', ['current_team' => $team->slug, $param => $record->id]))->assertNotFound();
    if ($entity === 'categories') {
        $this->postJson(route('categories.store', ['current_team' => $team->slug]), ['name' => 'Foreign parent', 'kind' => 'expense', 'parent_id' => $record->id])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    }
    expect($record->fresh()->name)->toBe($record->name);
    expect($record->fresh()->archived_at)->toBeNull();
})->with(['categories', 'payees']);

test('save actions independently enforce permissions and category dependency validation', function () {
    [$actor, $team] = referenceContext();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    expect(fn () => app(SavePayee::class)->handle($member, $team, ['name' => 'Denied']))->toThrow(AuthorizationException::class);
    expect(fn () => app(SaveTransactionCategory::class)->handle($member, $team, ['name' => 'Denied', 'kind' => 'expense']))->toThrow(AuthorizationException::class);
    $category = TransactionCategory::factory()->create(['team_id' => $team->id]);
    AccountTransaction::factory()->create(['financial_account_id' => FinancialAccount::factory()->create(['team_id' => $team->id])->id, 'category_id' => $category->id]);

    expect(fn () => app(SaveTransactionCategory::class)->handle($actor, $team, ['name' => $category->name, 'kind' => 'income'], $category))
        ->toThrow(ValidationException::class, 'The kind cannot change while transactions or subcategories reference this category.');
    expect($category->fresh()->kind)->toBe(TransactionCategoryKind::Expense);
});

test('catalog validation rejects invalid fields and tenant or archival injection', function (string $entity, array $changes, string $field) {
    [$actor, $team] = referenceContext();
    $input = array_replace(['name' => 'Reference', ...($entity === 'categories' ? ['kind' => 'expense'] : [])], $changes);

    $this->actingAs($actor)->postJson(route($entity.'.store', ['current_team' => $team->slug]), $input)->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount($entity === 'categories' ? 'transaction_categories' : 'payees', 0);
})->with([
    ['categories', ['name' => ''], 'name'], ['payees', ['name' => ''], 'name'],
    ['categories', ['kind' => 'transfer'], 'kind'], ['categories', ['team_id' => 900001], 'team_id'],
    ['payees', ['team_id' => 900001], 'team_id'], ['categories', ['archived_at' => '2026-10-01'], 'archived_at'],
    ['payees', ['archived_at' => '2026-10-01'], 'archived_at'],
]);

test('guest and outsider catalog access is denied', function (string $entity) {
    [$owner, $team] = referenceContext();

    $this->get(route($entity.'.index', ['current_team' => $team->slug]))->assertRedirectToRoute('login');
    $this->actingAs(User::factory()->create())->get(route($entity.'.index', ['current_team' => $team->slug]))->assertForbidden();
})->with(['categories', 'payees']);
