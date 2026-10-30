<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

dataset('malformed authentication emails', [
    'array' => [['test@example.com'], 'string'],
    'nested array' => [[['address' => 'test@example.com']], 'string'],
    'JSON object' => [(object) ['address' => 'test@example.com'], 'string'],
    'empty JSON object' => [(object) [], 'required'],
    'null' => [null, 'required'],
    'integer' => [123, 'string'],
    'decimal number' => [12.5, 'string'],
    'true' => [true, 'string'],
    'false' => [false, 'string'],
    'empty string' => ['', 'required'],
    'oversized string' => [str_repeat('A', 256).'@example.com', 'max.string'],
]);

test('authentication rejects malformed emails before normalization without changing credentials or reset tokens', function (string $endpoint, mixed $email, string $rule, bool $json) {
    if (! $json && $email instanceof stdClass) {
        $email = get_object_vars($email);
    }
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);
    $password = $user->password;
    $remember = $user->remember_token;
    Notification::fake();
    Event::fake([Login::class, PasswordReset::class]);
    Exceptions::fake();
    $back = $endpoint === 'login.store' ? route('login') : route('password.reset', ['token' => 'form-token']);
    $input = [
        'email' => $email, 'password' => $endpoint === 'login.store' ? 'password' : 'New-password-123!',
        'password_confirmation' => 'New-password-123!', 'token' => $token,
    ];

    $response = $json ? $this->postJson(route($endpoint), $input) : $this->from($back)->post(route($endpoint), $input);
    $message = __('validation.'.$rule, ['attribute' => 'email', 'max' => 255]);
    if ($json) {
        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => $message]);
    } else {
        $response->assertRedirect($back)->assertSessionHasErrors(['email' => $message])
            ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
    }

    $this->assertGuest();
    expect($user->fresh()->password === $password)->toBeTrue();
    expect($user->fresh()->remember_token === $remember)->toBeTrue();
    expect(Password::broker()->tokenExists($user, $token))->toBeTrue();
    $this->assertDatabaseCount('users', 1);
    Event::assertNotDispatched(Login::class);
    Event::assertNotDispatched(PasswordReset::class);
    Notification::assertNothingSent();
    Exceptions::assertNotReported(TypeError::class);
})->with(['login' => 'login.store', 'reset' => 'password.update'])
    ->with('malformed authentication emails')->with(['browser' => false, 'JSON' => true]);

test('compiled authentication routes reject malformed emails with the standard response', function (string $endpoint, bool $json) {
    $router = $this->app['router'];
    $router->setCompiledRoutes($router->getRoutes()->compile());
    $back = $endpoint === 'login.store' ? route('login') : route('password.reset', ['token' => 'form-token']);
    $input = ['email' => ['invalid'], 'password' => 'password', 'token' => 'invalid-probe'];

    $response = $json ? $this->postJson(route($endpoint), $input) : $this->from($back)->post(route($endpoint), $input);

    if ($json) {
        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    } else {
        $response->assertRedirect($back)->assertSessionHasErrors('email');
    }
    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('password_reset_tokens', 0);
})->with(['login' => 'login.store', 'reset' => 'password.update'])->with(['browser' => false, 'JSON' => true]);

test('missing authentication emails are rejected without creating records', function (string $endpoint) {
    $this->postJson(route($endpoint), ['password' => 'password', 'token' => 'invalid-probe'])
        ->assertUnprocessable()->assertJsonValidationErrors(['email' => __('validation.required', ['attribute' => 'email'])]);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('password_reset_tokens', 0);
})->with(['login' => 'login.store', 'reset' => 'password.update']);

test('mixed case login preserves session regeneration remember me and response contracts', function (bool $json, bool $compiled, bool $remember) {
    $user = User::factory()->create(['email' => 'login@example.com']);
    if ($compiled) {
        $router = $this->app['router'];
        $router->setCompiledRoutes($router->getRoutes()->compile());
    }
    $this->withSession(['preserved' => 'session-data']);
    $previousSession = $this->app['session.store']->getId();
    $input = ['email' => 'LOGIN@EXAMPLE.COM', 'password' => 'password', 'remember' => $remember];

    $response = $json ? $this->postJson(route('login.store'), $input) : $this->post(route('login.store'), $input);

    $this->assertAuthenticatedAs($user);
    expect($this->app['session.store']->getId() !== $previousSession)->toBeTrue();
    $response->assertSessionHas('preserved', 'session-data');
    if ($json) {
        $response->assertOk()->assertExactJson(['two_factor' => false]);
    } else {
        $response->assertRedirect(route('dashboard', ['current_team' => $user->personalTeam()->slug]));
    }
    $cookie = $this->app['auth']->guard()->getRecallerName();
    if ($remember) {
        $response->assertCookie($cookie);
    } else {
        $response->assertCookieMissing($cookie);
    }
})->with(['browser' => false, 'JSON' => true])->with(['normal routes' => false, 'compiled routes' => true])
    ->with(['session only' => false, 'remember me' => true]);

test('compiled mixed case login still requires the two factor challenge', function (bool $json) {
    $user = User::factory()->withTwoFactor()->create(['email' => 'twofactor@example.com']);
    $router = $this->app['router'];
    $router->setCompiledRoutes($router->getRoutes()->compile());
    $input = ['email' => 'TWOFACTOR@EXAMPLE.COM', 'password' => 'password', 'remember' => true];

    $response = $json ? $this->postJson(route('login.store'), $input) : $this->post(route('login.store'), $input);

    $this->assertGuest();
    $response->assertSessionHas('login.id', $user->id)->assertSessionHas('login.remember', true);
    if ($json) {
        $response->assertOk()->assertExactJson(['two_factor' => true]);
    } else {
        $response->assertRedirect(route('two-factor.login'));
    }
})->with(['browser' => false, 'JSON' => true]);

test('malformed login inputs share a bounded IP bucket without blocking valid email logins', function () {
    $user = User::factory()->create(['email' => 'valid@example.com']);
    foreach ([['invalid'], [['nested']], null, false, str_repeat('A', 256)] as $email) {
        $this->postJson(route('login.store'), ['email' => $email, 'password' => 'password'])->assertUnprocessable();
    }
    expect(RateLimiter::attempts(md5('loginmalformed-email|127.0.0.1')))->toBe(5);
    $this->postJson(route('login.store'), ['email' => 42, 'password' => 'password'])
        ->assertTooManyRequests()->assertHeader('Retry-After');
    $this->assertGuest();
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.2'])->postJson(route('login.store'), ['email' => ['invalid'], 'password' => 'password'])->assertUnprocessable();

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->postJson(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertOk();
    $this->assertAuthenticatedAs($user);
});

test('valid login keys retain case insensitive five per minute throttling and credential failure responses', function (bool $json) {
    $this->freezeTime();
    $user = User::factory()->create(['email' => 'limited@example.com']);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $input = ['email' => $attempt % 2 === 0 ? 'LIMITED@EXAMPLE.COM' : $user->email, 'password' => 'wrong-password'];
        $response = $json ? $this->postJson(route('login.store'), $input) : $this->from(route('login'))->post(route('login.store'), $input);
        if ($json) {
            $response->assertUnprocessable()->assertJsonValidationErrors(['email' => __('auth.failed')]);
        } else {
            $response->assertRedirect(route('login'))->assertSessionHasErrors(['email' => __('auth.failed')]);
        }
    }
    expect(RateLimiter::attempts(md5('loginlimited@example.com|127.0.0.1')))->toBe(5);
    $input = ['email' => $user->email, 'password' => 'password'];
    ($json ? $this->postJson(route('login.store'), $input) : $this->post(route('login.store'), $input))
        ->assertTooManyRequests()->assertHeader('Retry-After');
    $this->assertGuest();

    $this->travel(61)->seconds();
    $response = $json ? $this->postJson(route('login.store'), $input) : $this->post(route('login.store'), $input);
    if ($json) {
        $response->assertOk();
    } else {
        $response->assertRedirect(route('dashboard', ['current_team' => $user->personalTeam()->slug]));
    }
    $this->assertAuthenticatedAs($user);
})->with(['browser' => false, 'JSON' => true]);

test('mixed case password resets preserve completion token single use and response contracts', function (bool $json, bool $compiled) {
    $user = User::factory()->create(['email' => 'reset@example.com']);
    $token = Password::broker()->createToken($user);
    $remember = $user->remember_token;
    Event::fake([PasswordReset::class]);
    if ($compiled) {
        $router = $this->app['router'];
        $router->setCompiledRoutes($router->getRoutes()->compile());
    }
    $input = ['email' => 'RESET@EXAMPLE.COM', 'token' => $token, 'password' => 'New-password-123!', 'password_confirmation' => 'New-password-123!'];

    $response = $json ? $this->postJson(route('password.update'), $input) : $this->from(route('password.request'))->post(route('password.update'), $input);

    if ($json) {
        $response->assertOk()->assertExactJson(['message' => __('passwords.reset')]);
    } else {
        $response->assertRedirect(route('login'))->assertSessionHas('status', __('passwords.reset'));
    }
    $this->assertGuest();
    expect(Hash::check('New-password-123!', $user->fresh()->password))->toBeTrue();
    expect(Hash::check('password', $user->fresh()->password))->toBeFalse();
    expect($user->fresh()->remember_token !== $remember)->toBeTrue();
    expect(Password::broker()->tokenExists($user, $token))->toBeFalse();
    $this->assertDatabaseCount('password_reset_tokens', 0);
    Event::assertDispatchedTimes(PasswordReset::class, 1);

    $input['password'] = $input['password_confirmation'] = 'Another-password-123!';
    $response = $json ? $this->postJson(route('password.update'), $input) : $this->from(route('password.request'))->post(route('password.update'), $input);
    if ($json) {
        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => __('passwords.token')]);
    } else {
        $response->assertRedirect(route('password.request'))->assertSessionHasErrors(['email' => __('passwords.token')]);
    }
    expect(Hash::check('New-password-123!', $user->fresh()->password))->toBeTrue();
    Event::assertDispatchedTimes(PasswordReset::class, 1);
})->with(['browser' => false, 'JSON' => true])->with(['normal routes' => false, 'compiled routes' => true]);

test('invalid or expired reset tokens cannot change passwords', function (bool $expired) {
    $this->freezeTime();
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);
    $before = $user->password;
    Event::fake([PasswordReset::class]);
    if ($expired) {
        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();
    }

    $this->postJson(route('password.update'), ['email' => strtoupper($user->email), 'token' => $expired ? $token : 'invalid-probe', 'password' => 'New-password-123!', 'password_confirmation' => 'New-password-123!'])
        ->assertUnprocessable()->assertJsonValidationErrors(['email' => __('passwords.token')]);

    $this->assertGuest();
    expect($user->fresh()->password === $before)->toBeTrue();
    expect(Password::broker()->tokenExists($user, $token))->toBe(! $expired);
    $this->assertDatabaseCount('password_reset_tokens', 1);
    Event::assertNotDispatched(PasswordReset::class);
})->with(['invalid' => false, 'expired' => true]);

test('reset password and token validation failures preserve the valid token', function (string $invalid) {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);
    $before = $user->password;
    $input = ['email' => strtoupper($user->email), 'token' => $token, 'password' => 'New-password-123!', 'password_confirmation' => 'New-password-123!'];
    if ($invalid === 'short password') {
        $input['password'] = $input['password_confirmation'] = 'short';
    } elseif ($invalid === 'confirmation') {
        $input['password_confirmation'] = 'different';
    } else {
        unset($input['token']);
    }

    $this->postJson(route('password.update'), $input)->assertUnprocessable()->assertJsonValidationErrors($invalid === 'missing token' ? 'token' : 'password');

    $this->assertGuest();
    expect($user->fresh()->password === $before)->toBeTrue();
    expect(Password::broker()->tokenExists($user, $token))->toBeTrue();
})->with(['short password', 'confirmation', 'missing token']);

test('authenticated users retain guest route redirects for malformed authentication input', function (string $endpoint) {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route($endpoint), ['email' => ['invalid']])
        ->assertRedirect(route('dashboard', ['current_team' => $user->personalTeam()->slug]));

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('users', 1);
})->with(['login' => 'login.store', 'reset' => 'password.update']);

test('reset link requests retain existing validation normalization and token creation', function () {
    $user = User::factory()->create(['email' => 'link@example.com']);
    Notification::fake();

    $this->postJson(route('password.email'), ['email' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->assertDatabaseCount('password_reset_tokens', 0);
    Notification::assertNothingSent();
    $this->postJson(route('password.email'), ['email' => 'LINK@EXAMPLE.COM'])->assertOk();

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
    $this->assertDatabaseCount('password_reset_tokens', 1);
    $this->assertGuest();
});

test('compiled passkey login options remain accessible without an email input', function () {
    $router = $this->app['router'];
    $router->setCompiledRoutes($router->getRoutes()->compile());

    $this->getJson(route('passkey.login-options'))->assertOk()->assertJsonStructure(['options' => ['challenge']]);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});
