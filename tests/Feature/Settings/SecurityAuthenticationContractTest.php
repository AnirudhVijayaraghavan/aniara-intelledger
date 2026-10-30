<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\SecurityController;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;

test('security settings reject missing or incompatible principals without changing the globally authenticated user', function (string $operation, bool $missing) {
    $user = User::factory()->create();
    $this->actingAs($user);
    $before = $user->fresh()->getAttributes();
    $request = $operation === 'edit' ? new TwoFactorAuthenticationRequest : new PasswordUpdateRequest;
    $request->setUserResolver(fn () => $missing ? null : new GenericUser(['id' => $user->id]));

    expect(fn () => app(SecurityController::class)->{$operation}($request))->toThrow(AuthenticationException::class, 'Unauthenticated.');

    expect($user->fresh()->getAttributes())->toBe($before);
})->with(['edit', 'update'])->with([false, true]);

test('security page passkeys and TOTP status belong to the request user instead of the global guard', function () {
    config(['fortify.features' => [Features::twoFactorAuthentication(['confirm' => true]), Features::passkeys()]]);
    $globalUser = User::factory()->create();
    $actor = User::factory()->withTwoFactor()->create();
    $key = $actor->passkeys()->create(['name' => 'Request key', 'credential_id' => 'request-key', 'credential' => []]);
    $globalUser->passkeys()->create(['name' => 'Global key', 'credential_id' => 'global-key', 'credential' => []]);
    $globalBefore = $globalUser->fresh()->getAttributes();
    $actorBefore = $actor->fresh()->getAttributes();
    $this->actingAs($globalUser);
    $request = new TwoFactorAuthenticationRequest;
    $request->setUserResolver(fn () => $actor);
    $request->setLaravelSession(app('session')->driver());
    $request->headers->set('X-Inertia', 'true');

    $props = app(SecurityController::class)->edit($request)->toResponse($request)->getData(true)['props'];

    expect($props['twoFactorEnabled'])->toBeTrue();
    expect($props['passkeys'])->toHaveCount(1);
    expect($props['passkeys'][0]['id'])->toBe($key->id);
    expect($props['passkeys'][0]['last_used_at_diff'])->toBeNull();
    expect($globalUser->fresh()->getAttributes())->toBe($globalBefore);
    expect($actor->fresh()->getAttributes())->toBe($actorBefore);
});

test('password update changes only the explicit request user after normal password field validation', function () {
    $globalUser = User::factory()->create();
    $actor = User::factory()->create();
    $before = $globalUser->fresh()->getAttributes();
    $this->actingAs($globalUser);
    $request = new PasswordUpdateRequest([
        'current_password' => 'password', 'password' => 'new-contract-password', 'password_confirmation' => 'new-contract-password',
    ]);
    $request->setUserResolver(fn () => $actor);
    $validator = Validator::make($request->all(), $request->rules());
    $validator->validate();
    $request->setValidator($validator);

    app(SecurityController::class)->update($request);

    expect(Hash::check('new-contract-password', $actor->fresh()->password))->toBeTrue();
    expect($globalUser->fresh()->getAttributes())->toBe($before);
});

test('security and team routes retain guest browser redirects and JSON authentication failures', function (string $operation, bool $json) {
    $owner = User::factory()->create();
    $url = match ($operation) {
        'team' => route('teams.edit', $owner->personalTeam()), 'security' => route('security.edit'), 'password' => route('user-password.update')
    };
    $method = ($operation === 'password' ? 'put' : 'get').($json ? 'Json' : '');

    $response = $this->{$method}($url);

    if ($json) {
        $response->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    } else {
        $response->assertRedirectToRoute('login');
    }
})->with(['team', 'security', 'password'])->with([false, true]);

test('passkey discovery stays intentionally guest accessible', function () {
    $this->getJson(route('well-known.passkeys'))->assertOk()->assertExactJson([
        'enroll' => route('security.edit'), 'manage' => route('security.edit'),
    ]);
});

test('security display preserves optional passkey metadata and the relative creation timestamp contract', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-27T12:00:00Z'));
    config(['fortify.features' => [Features::passkeys()]]);
    $user = User::factory()->create();
    $key = $user->passkeys()->create(['name' => 'Laptop', 'credential_id' => 'display-key', 'credential' => []]);
    $key->created_at = now()->subDays(2);
    $key->save();

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('passkeys.0.created_at_diff', '2 days ago')
            ->where('passkeys.0.last_used_at_diff', null)->where('passkeys.0.authenticator', null)
            ->missing('passkeys.0.credential')->missing('passkeys.0.credential_id'));
});

test('security display rejects absent or invalid required passkey creation timestamps without changing credentials', function (?string $value) {
    config(['fortify.features' => [Features::passkeys()]]);
    $user = User::factory()->create();
    $key = $user->passkeys()->create(['name' => 'Laptop', 'credential_id' => 'invalid-date-key', 'credential' => []]);
    $stored = DB::table('passkeys')->where('id', $key->id)->value('created_at');
    Exceptions::fake();
    Event::listen('eloquent.retrieved: '.Passkey::class, function (Passkey $record) use ($value): void {
        $record->setRawAttributes(array_replace($record->getAttributes(), ['created_at' => $value]));
    });

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->getJson(route('security.edit'))->assertInternalServerError();

    Exceptions::assertReported(fn (UnexpectedValueException $exception): bool => $exception->getMessage() === 'Passkey creation timestamp must be valid.');
    expect(DB::table('passkeys')->where('id', $key->id)->value('created_at'))->toBe($stored);
    $this->assertModelExists($key);
})->with([null, '0000-01-01 00:00:00']);
