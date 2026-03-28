<?php

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('home page returns the fintech landing page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('canRegister', Features::enabled(Features::registration()))
            ->etc()
        );
});

test('feature tests use an isolated in-memory database', function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
});
