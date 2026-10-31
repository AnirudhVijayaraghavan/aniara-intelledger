<?php

declare(strict_types=1);

use App\Actions\Finance\ArchivePayee;
use App\Actions\Finance\SavePayee;
use App\Enums\TeamRole;
use App\Http\Controllers\Finance\PayeeController;
use App\Http\Requests\Finance\ArchivePayeeRequest;
use App\Http\Requests\Finance\SavePayeeRequest;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Inertia\Testing\AssertableInertia as Assert;

test('payee management uses the authenticated user and explicit household despite a different active preference', function (TeamRole $role) {
    $actor = User::factory()->create();
    $personal = $actor->personalTeam();
    $team = User::factory()->create()->personalTeam();
    $team->members()->attach($actor, ['role' => $role->value]);
    $personalPayee = Payee::factory()->create(['team_id' => $personal->id]);
    $personalBefore = $personalPayee->fresh()->getAttributes();

    $this->actingAs($actor)->post(route('payees.store', ['current_team' => $team->slug]), [
        'name' => 'Household merchant',
    ])->assertSessionHasNoErrors()->assertRedirectToRoute('payees.index', ['current_team' => $team->slug]);
    $payee = $team->payees()->sole();
    $params = ['current_team' => $team->slug, 'payee' => $payee->id];
    $this->put(route('payees.update', $params), ['name' => 'Shared merchant'])
        ->assertSessionHasNoErrors()->assertRedirectToRoute('payees.index', ['current_team' => $team->slug]);
    $this->post(route('payees.archive', $params))
        ->assertSessionHasNoErrors()->assertRedirectToRoute('payees.index', ['current_team' => $team->slug]);
    $this->get(route('payees.index', ['current_team' => $team->slug, 'status' => 'archived']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('payees/index')
        ->where('team.id', $team->id)->where('canManage', true)
        ->where('records.total', 1)->where('records.data.0.id', $payee->id));

    $this->assertDatabaseHas('payees', ['id' => $payee->id, 'team_id' => $team->id, 'name' => 'Shared merchant']);
    expect($payee->fresh()->archived_at)->not->toBeNull();
    expect($personalPayee->fresh()->getAttributes())->toBe($personalBefore);
})->with([TeamRole::Owner, TeamRole::Admin]);

test('guest payee mutations preserve login redirects and JSON authentication errors without writes', function (string $operation, bool $json) {
    $payee = Payee::factory()->create();
    $params = ['current_team' => $payee->team->slug];
    if ($operation !== 'store') {
        $params['payee'] = $payee->id;
    }
    $before = Payee::orderBy('id')->get()->toArray();
    $method = ($operation === 'update' ? 'put' : 'post').($json ? 'Json' : '');

    $response = $this->{$method}(route('payees.'.$operation, $params), ['name' => 'Denied']);

    if ($json) {
        $response->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    } else {
        $response->assertRedirectToRoute('login');
    }
    expect(Payee::orderBy('id')->get()->toArray())->toBe($before);
})->with(['store', 'update', 'archive'])->with([false, true]);

test('guest payee JSON listing returns an authentication error without household data', function () {
    $payee = Payee::factory()->create();

    $this->getJson(route('payees.index', ['current_team' => $payee->team->slug]))
        ->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
});

test('owning a personal household does not authorize payee mutations in another household', function () {
    $actor = User::factory()->create();
    $payee = Payee::factory()->create();
    $params = ['current_team' => $payee->team->slug, 'payee' => $payee->id];
    $before = Payee::orderBy('id')->get()->toArray();

    $this->actingAs($actor)->postJson(route('payees.store', ['current_team' => $payee->team->slug]), ['name' => 'Denied'])->assertForbidden();
    $this->putJson(route('payees.update', $params), ['name' => 'Denied'])->assertForbidden();
    $this->postJson(route('payees.archive', $params))->assertForbidden();
    $this->getJson(route('payees.index', ['current_team' => $payee->team->slug]))->assertForbidden();

    expect(Payee::orderBy('id')->get()->toArray())->toBe($before);
});

test('payee action handoff rejects missing or foreign request principals instead of falling back to the global guard', function (string $operation, bool $missing) {
    $actor = User::factory()->create();
    $team = $actor->personalTeam();
    $payee = Payee::factory()->create(['team_id' => $team->id]);
    $before = Payee::orderBy('id')->get()->toArray();
    $this->actingAs($actor);
    $principal = $missing ? null : new GenericUser(['id' => $actor->id]);
    $controller = app(PayeeController::class);

    if ($operation === 'archive') {
        $request = new ArchivePayeeRequest;
        $request->setUserResolver(fn () => $principal);
        $call = fn () => $controller->archive($request, $team, $payee, app(ArchivePayee::class));
    } else {
        $request = new SavePayeeRequest;
        $request->setUserResolver(fn () => $principal);
        $call = $operation === 'store'
            ? fn () => $controller->store($request, $team, app(SavePayee::class))
            : fn () => $controller->update($request, $team, $payee, app(SavePayee::class));
    }

    expect($call)->toThrow(AuthenticationException::class, 'Unauthenticated.');
    expect(Payee::orderBy('id')->get()->toArray())->toBe($before);
})->with(['store', 'update', 'archive'])->with([false, true]);

test('payee handoff authorizes the resolved member rather than the privileged global guard', function () {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $payee = Payee::factory()->create(['team_id' => $team->id]);
    $before = $payee->fresh()->getAttributes();
    $this->actingAs($owner);
    $request = new ArchivePayeeRequest;
    $request->setUserResolver(fn () => $member);

    expect(fn () => app(PayeeController::class)->archive($request, $team, $payee, app(ArchivePayee::class)))
        ->toThrow(AuthorizationException::class);
    expect($payee->fresh()->getAttributes())->toBe($before);
});
