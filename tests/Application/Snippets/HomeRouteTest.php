<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

it('renders the snippet runner as the home page', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Snippets/Run'));
});

it('shows the running PHP and Laravel version', function (): void {
    $this->get('/')
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('phpVersion', PHP_VERSION)
                ->where('laravelVersion', app()->version()),
        );
});
