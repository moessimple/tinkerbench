<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

test('renders the snippet runner as the home page', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Snippets/Run'));
});
