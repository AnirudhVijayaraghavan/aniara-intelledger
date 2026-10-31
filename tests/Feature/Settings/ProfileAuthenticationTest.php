<?php

declare(strict_types=1);

use App\Actions\Fortify\DeleteUser;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

test('profile boundaries reject missing or incompatible request principals without modifying the globally authenticated user', function (string $operation, bool $missing) {
    $user = User::factory()->create();
    $before = $user->fresh()->getAttributes();
    $this->actingAs($user);
    $principal = $missing ? null : new GenericUser(['id' => $user->id, 'email' => $user->email]);
    $request = match ($operation) {
        'edit' => new Request,
        'update' => new ProfileUpdateRequest,
        'destroy' => new ProfileDeleteRequest,
    };
    $request->setUserResolver(fn () => $principal);
    $controller = app(ProfileController::class);
    $call = $operation === 'destroy'
        ? fn () => $controller->destroy($request, app(DeleteUser::class))
        : fn () => $controller->{$operation}($request);

    expect($call)->toThrow(AuthenticationException::class, 'Unauthenticated.');
    expect($user->fresh()->getAttributes())->toBe($before);
    $this->assertAuthenticatedAs($user);
})->with(['edit', 'update', 'destroy'])->with([false, true]);

test('profile validation rejects missing or incompatible principals before building user specific uniqueness rules', function (bool $missing) {
    $user = User::factory()->create();
    $this->actingAs($user);
    $request = new ProfileUpdateRequest;
    $request->setUserResolver(fn () => $missing ? null : new GenericUser(['id' => $user->id]));
    $request->setContainer(app())->setRedirector(app('redirect'));

    expect(fn () => $request->validateResolved())->toThrow(AuthenticationException::class, 'Unauthenticated.');
})->with([false, true]);

test('profile validation and updates use the request user rather than the globally authenticated user', function () {
    $globalUser = User::factory()->create();
    $actor = User::factory()->create();
    $globalBefore = $globalUser->fresh()->getAttributes();
    $verifiedAt = $actor->email_verified_at;
    $this->actingAs($globalUser);
    $request = ProfileUpdateRequest::create('/settings/profile', 'PATCH', ['name' => 'Request principal', 'email' => $actor->email]);
    $request->setUserResolver(fn () => $actor);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    $response = app(ProfileController::class)->update($request);

    expect($response->getTargetUrl())->toBe(route('profile.edit'));
    expect($actor->fresh()->name)->toBe('Request principal');
    expect($actor->fresh()->email_verified_at->equalTo($verifiedAt))->toBeTrue();
    expect($globalUser->fresh()->getAttributes())->toBe($globalBefore);
});

test('profile routes retain JSON guest authentication responses', function (string $operation) {
    $method = match ($operation) {
        'edit' => 'getJson', 'update' => 'patchJson', 'destroy' => 'deleteJson'
    };

    $this->{$method}(route('profile.'.$operation))->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
})->with(['edit', 'update', 'destroy']);

test('unverified users retain profile onboarding access while financial dashboards still require verification', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/profile')->where('mustVerifyEmail', true));
    $this->patch(route('profile.update'), ['name' => 'Unverified member', 'email' => $user->email])
        ->assertSessionHasNoErrors()->assertRedirectToRoute('profile.edit');
    $this->get(route('dashboard', ['current_team' => $user->personalTeam()->slug]))->assertRedirectToRoute('verification.notice');

    expect($user->fresh()->name)->toBe('Unverified member');
    expect($user->fresh()->email_verified_at)->toBeNull();
});
