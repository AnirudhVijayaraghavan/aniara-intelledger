<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the financial ledger welcome page can be rendered', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('auth.user')
        );
});
