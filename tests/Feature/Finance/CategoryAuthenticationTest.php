<?php

declare(strict_types=1);

use App\Actions\Finance\ArchiveTransactionCategory;
use App\Actions\Finance\SaveTransactionCategory;
use App\Enums\TeamRole;
use App\Http\Controllers\Finance\TransactionCategoryController;
use App\Http\Requests\Finance\ArchiveTransactionCategoryRequest;
use App\Http\Requests\Finance\SaveTransactionCategoryRequest;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Inertia\Testing\AssertableInertia as Assert;

test('category management uses the authenticated user and explicit household despite a different active preference', function (TeamRole $role) {
    $actor = User::factory()->create();
    $personal = $actor->personalTeam();
    $team = User::factory()->create()->personalTeam();
    $team->members()->attach($actor, ['role' => $role->value]);
    $personalCategory = TransactionCategory::factory()->create(['team_id' => $personal->id]);
    $personalBefore = $personalCategory->fresh()->getAttributes();

    $this->actingAs($actor)->post(route('categories.store', ['current_team' => $team->slug]), [
        'name' => 'Household food', 'kind' => 'expense', 'parent_id' => null,
    ])->assertSessionHasNoErrors()->assertRedirectToRoute('categories.index', ['current_team' => $team->slug]);
    $category = $team->transactionCategories()->sole();
    $params = ['current_team' => $team->slug, 'transaction_category' => $category->id];
    $this->put(route('categories.update', $params), ['name' => 'Shared groceries', 'kind' => 'expense', 'parent_id' => null])
        ->assertSessionHasNoErrors()->assertRedirectToRoute('categories.index', ['current_team' => $team->slug]);
    $this->post(route('categories.archive', $params))
        ->assertSessionHasNoErrors()->assertRedirectToRoute('categories.index', ['current_team' => $team->slug]);
    $this->get(route('categories.index', ['current_team' => $team->slug, 'status' => 'archived']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('categories/index')
        ->where('team.id', $team->id)->where('canManage', true)
        ->where('records.total', 1)->where('records.data.0.id', $category->id));

    $this->assertDatabaseHas('transaction_categories', ['id' => $category->id, 'team_id' => $team->id, 'name' => 'Shared groceries']);
    expect($category->fresh()->archived_at)->not->toBeNull();
    expect($personalCategory->fresh()->getAttributes())->toBe($personalBefore);
})->with([TeamRole::Owner, TeamRole::Admin]);

test('guest category mutations preserve login redirects and JSON authentication errors without writes', function (string $operation, bool $json) {
    $category = TransactionCategory::factory()->create();
    $params = ['current_team' => $category->team->slug];
    if ($operation !== 'store') {
        $params['transaction_category'] = $category->id;
    }
    $before = TransactionCategory::orderBy('id')->get()->toArray();
    $method = ($operation === 'update' ? 'put' : 'post').($json ? 'Json' : '');

    $response = $this->{$method}(route('categories.'.$operation, $params), ['name' => 'Denied', 'kind' => 'expense']);

    if ($json) {
        $response->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    } else {
        $response->assertRedirectToRoute('login');
    }
    expect(TransactionCategory::orderBy('id')->get()->toArray())->toBe($before);
})->with(['store', 'update', 'archive'])->with([false, true]);

test('guest category JSON listing returns an authentication error without household data', function () {
    $category = TransactionCategory::factory()->create();

    $this->getJson(route('categories.index', ['current_team' => $category->team->slug]))
        ->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
});

test('owning a personal household does not authorize category mutations in another household', function () {
    $actor = User::factory()->create();
    $category = TransactionCategory::factory()->create();
    $params = ['current_team' => $category->team->slug, 'transaction_category' => $category->id];
    $before = TransactionCategory::orderBy('id')->get()->toArray();

    $this->actingAs($actor)->postJson(route('categories.store', ['current_team' => $category->team->slug]), ['name' => 'Denied', 'kind' => 'expense'])->assertForbidden();
    $this->putJson(route('categories.update', $params), ['name' => 'Denied', 'kind' => 'expense'])->assertForbidden();
    $this->postJson(route('categories.archive', $params))->assertForbidden();
    $this->getJson(route('categories.index', ['current_team' => $category->team->slug]))->assertForbidden();

    expect(TransactionCategory::orderBy('id')->get()->toArray())->toBe($before);
});

test('category action handoff rejects missing or foreign request principals instead of falling back to the global guard', function (string $operation, bool $missing) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $category = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $before = TransactionCategory::orderBy('id')->get()->toArray();
    $this->actingAs($actor);
    $principal = $missing ? null : new GenericUser(['id' => $actor->id]);
    $controller = app(TransactionCategoryController::class);

    if ($operation === 'archive') {
        $request = new ArchiveTransactionCategoryRequest;
        $request->setUserResolver(fn () => $principal);
        $call = fn () => $controller->archive($request, $team, $category, app(ArchiveTransactionCategory::class));
    } else {
        $request = new SaveTransactionCategoryRequest;
        $request->setUserResolver(fn () => $principal);
        $call = $operation === 'store'
            ? fn () => $controller->store($request, $team, app(SaveTransactionCategory::class))
            : fn () => $controller->update($request, $team, $category, app(SaveTransactionCategory::class));
    }

    expect($call)->toThrow(AuthenticationException::class, 'Unauthenticated.');
    expect(TransactionCategory::orderBy('id')->get()->toArray())->toBe($before);
})->with(['store', 'update', 'archive'])->with([false, true]);

test('category handoff authorizes the resolved member rather than the privileged global guard', function () {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $category = TransactionCategory::factory()->create(['team_id' => $team->id]);
    $before = $category->fresh()->getAttributes();
    $this->actingAs($owner);
    $request = new ArchiveTransactionCategoryRequest;
    $request->setUserResolver(fn () => $member);

    expect(fn () => app(TransactionCategoryController::class)->archive($request, $team, $category, app(ArchiveTransactionCategory::class)))
        ->toThrow(AuthorizationException::class);
    expect($category->fresh()->getAttributes())->toBe($before);
});
