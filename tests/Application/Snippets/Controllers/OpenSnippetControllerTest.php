<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('snippets');
});

it('renders the scratch snippet by default', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Snippets/Run')
                ->where('snippetName', 'scratch')
                ->where('content', "echo 'Hello, world!';"),
        );
});

it('loads the content of an existing named snippet', function (): void {
    Storage::disk('snippets')->put('my-snippet.php', 'echo "existing";');

    $this->get('/my-snippet')
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('snippetName', 'my-snippet')
                ->where('content', 'echo "existing";'),
        );
});

it('creates a missing named snippet with default content when opened', function (): void {
    $this->get('/new-snippet')
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('snippetName', 'new-snippet')
                ->where('content', "echo 'Hello, world!';"),
        );

    Storage::disk('snippets')->assertExists('new-snippet.php');
});

it('shows the running PHP and Laravel version', function (): void {
    $this->get('/')
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('phpVersion', PHP_VERSION)
                ->where('laravelVersion', app()->version()),
        );
});
