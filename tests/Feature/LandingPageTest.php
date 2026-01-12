<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the IntelLedger landing page', function () {
    $response = $this->get(route('home'));

    $response->assertSuccessful();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('welcome')
        ->where('canRegister', fn ($value) => is_bool($value))
    );
});
