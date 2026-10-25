<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

test('login signup and authenticated pages receive the current version and see subsequent bumps', function () {
    $path = tempnam(sys_get_temp_dir(), 'intelledger-version-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary version file.');
    }

    File::put($path, '2.3.4'.PHP_EOL);
    config(['app.version_file' => $path]);

    try {
        $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/login')->where('appVersion', '2.3.4'));
        $this->get(route('register'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/register')->where('appVersion', '2.3.4'));

        $this->artisan('app:version', ['bump' => 'patch'])->assertSuccessful();
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('appVersion', '2.3.5'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('appVersion', '2.3.5'));
    } finally {
        File::delete($path);
    }
});

test('shared version data works when cached configuration lacks the new version setting', function () {
    $appConfiguration = config('app');
    unset($appConfiguration['version_file']);
    config(['app' => $appConfiguration]);

    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('appVersion', trim(File::get(base_path('VERSION')))));
});
