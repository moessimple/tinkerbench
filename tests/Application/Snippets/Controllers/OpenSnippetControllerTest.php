<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use Mockery\MockInterface;
use Support\SnippetRepository;

it('opens the default scratch snippet', function (): void {
    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists')->once()->with('scratch');
        $mock->shouldReceive('contents')->once()->with('scratch')->andReturn("echo 'Hello, world!';");
    });

    $this->get('/')
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Snippets/Run')
                ->where('snippetName', 'scratch')
                ->where('content', "echo 'Hello, world!';"),
        );
});

it('opens the named snippet from the URL', function (): void {
    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists')->once()->with('my-snippet');
        $mock->shouldReceive('contents')->once()->with('my-snippet')->andReturn('echo "existing";');
    });

    $this->get('/my-snippet')
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('snippetName', 'my-snippet')
                ->where('content', 'echo "existing";'),
        );
});

it('shows the running PHP and Laravel version', function (): void {
    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists');
        $mock->shouldReceive('contents')->andReturn('');
    });

    $this->get('/')
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('phpVersion', PHP_VERSION)
                ->where('laravelVersion', app()->version()),
        );
});
