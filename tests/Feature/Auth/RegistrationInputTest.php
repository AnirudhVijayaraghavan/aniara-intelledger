<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

dataset('malformed registration emails', [
    'array' => [['address@example.com'], 'string'],
    'nested array' => [[['address' => 'address@example.com']], 'string'],
    'JSON object' => [(object) ['address' => 'address@example.com'], 'string'],
    'empty JSON object' => [(object) [], 'required'],
    'empty array' => [[], 'required'],
    'null' => [null, 'required'],
    'integer' => [123, 'string'],
    'zero' => [0, 'string'],
    'decimal number' => [12.5, 'string'],
    'true' => [true, 'string'],
    'false' => [false, 'string'],
    'oversized string' => [str_repeat('A', 256).'@example.com', 'max.string'],
]);

test('registration rejects malformed emails as JSON validation errors without partial records', function (mixed $email, string $rule) {
    Event::fake([Registered::class]);
    Notification::fake();

    $this->postJson(route('register.store'), [
        'name' => 'Test User', 'email' => $email,
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'email' => __('validation.'.$rule, ['attribute' => 'email', 'max' => 255]),
    ]);

    $this->assertGuest();
    foreach (['users', 'teams', 'team_members', 'team_invitations'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    Event::assertNotDispatched(Registered::class);
    Notification::assertNothingSent();
})->with('malformed registration emails');

test('registration redirects browser email validation failures without partial records', function (mixed $email, string $rule) {
    Event::fake([Registered::class]);
    Notification::fake();

    $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Test User', 'email' => $email,
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertRedirect(route('register'))->assertSessionHasErrors([
        'email' => __('validation.'.$rule, ['attribute' => 'email', 'max' => 255]),
    ])->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');

    $this->assertGuest();
    foreach (['users', 'teams', 'team_members', 'team_invitations'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    Event::assertNotDispatched(Registered::class);
    Notification::assertNothingSent();
})->with([
    'array' => [['address@example.com'], 'string'],
    'nested array' => [[['address@example.com']], 'string'],
    'null' => [null, 'required'],
    'boolean' => [false, 'string'],
    'oversized string' => [str_repeat('A', 256).'@example.com', 'max.string'],
]);

test('mixed case registration preserves normalization verification ownership and response contracts', function (bool $json) {
    Notification::fake();
    $registered = [];
    Event::listen(Registered::class, function (Registered $event) use (&$registered): void {
        $registered[] = $event->user->getAuthIdentifier();
    });
    $input = [
        'name' => 'Test User', 'email' => 'Mixed.Case@EXAMPLE.COM',
        'password' => 'password', 'password_confirmation' => 'password',
    ];

    $response = $json
        ? $this->postJson(route('register.store'), $input)
        : $this->post(route('register.store'), $input);

    $user = User::query()->where('email', 'mixed.case@example.com')->sole();
    $this->assertAuthenticatedAs($user);
    expect($user->email_verified_at)->toBeNull();
    expect(Hash::check('password', $user->password))->toBeTrue();
    expect($registered)->toBe([$user->id]);
    $team = $user->personalTeam();
    expect($team)->not->toBeNull();
    expect($user->current_team_id)->toBe($team->id);
    expect($user->teamRole($team))->toBe(TeamRole::Owner);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('teams', 1);
    $this->assertDatabaseCount('team_members', 1);
    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    if ($json) {
        $response->assertCreated()->assertExactJson(['two_factor' => false]);
    } else {
        $response->assertRedirect(route('dashboard', ['current_team' => $team->slug]));
    }
    $this->get(route('appearance.edit'))->assertRedirect(route('verification.notice'));
})->with(['browser' => false, 'JSON' => true]);

test('registration uniqueness is checked after mixed case email normalization', function (bool $json) {
    $user = User::factory()->create(['email' => 'existing@example.com']);
    Notification::fake();
    Event::fake([Registered::class]);
    $input = [
        'name' => 'Another User', 'email' => 'EXISTING@EXAMPLE.COM',
        'password' => 'password', 'password_confirmation' => 'password',
    ];
    $response = $json
        ? $this->postJson(route('register.store'), $input)
        : $this->from(route('register'))->post(route('register.store'), $input);

    if ($json) {
        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => __('validation.unique', ['attribute' => 'email'])]);
    } else {
        $response->assertRedirect(route('register'))->assertSessionHasErrors(['email' => __('validation.unique', ['attribute' => 'email'])]);
    }
    $this->assertGuest();
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('teams', 1);
    $this->assertDatabaseCount('team_members', 1);
    expect($user->fresh()->email)->toBe('existing@example.com');
    Event::assertNotDispatched(Registered::class);
    Notification::assertNothingSent();
})->with(['browser' => false, 'JSON' => true]);

test('registration retains name email syntax and password validation after shape validation', function (string $field, mixed $value) {
    $input = [
        'name' => 'Test User', 'email' => 'test@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ];
    $input[$field] = $value;
    if ($field === 'password') {
        $input['password_confirmation'] = $value;
    }

    $this->postJson(route('register.store'), $input)->assertUnprocessable()->assertJsonValidationErrors($field === 'password_confirmation' ? 'password' : $field);

    $this->assertGuest();
    foreach (['users', 'teams', 'team_members'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
})->with([
    'missing name' => ['name', null],
    'invalid syntax' => ['email', 'not-an-email'],
    'missing password' => ['password', null],
    'short password' => ['password', 'short'],
    'password confirmation' => ['password_confirmation', 'different'],
]);

test('registration guest middleware still redirects authenticated users before email validation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('register.store'), [
        'email' => ['invalid'],
    ])->assertRedirect(route('dashboard', ['current_team' => $user->personalTeam()->slug]));

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('teams', 1);
    $this->assertDatabaseCount('team_members', 1);
});

test('registration email validation still runs when the router uses compiled routes', function () {
    $router = $this->app['router'];
    $router->setCompiledRoutes($router->getRoutes()->compile());

    $this->postJson(route('register.store'), [
        'name' => 'Test User', 'email' => ['test@example.com'],
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->assertGuest();
    foreach (['users', 'teams', 'team_members'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
});

test('registration without an email uses the normal required validation response', function () {
    $this->postJson(route('register.store'), [
        'name' => 'Test User', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'email' => __('validation.required', ['attribute' => 'email']),
    ]);

    $this->assertGuest();
    foreach (['users', 'teams', 'team_members'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
});
