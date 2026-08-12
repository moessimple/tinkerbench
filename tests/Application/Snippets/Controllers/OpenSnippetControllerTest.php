<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use Mockery\MockInterface;
use Support\Herd;
use Support\SnippetRepository;

it('opens the default scratch snippet for the current project', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('currentProject')->once()->andReturn('my-project');
        $mock->shouldReceive('projectPath')->with('my-project')->andReturn('/path/to/project');
        $mock->shouldReceive('phpBinary')->with('my-project')->andReturn('/path/to/php');
        $mock->shouldReceive('phpVersion')->andReturn('8.5.0');
        $mock->shouldReceive('laravelVersion')->andReturn('13.0.0');
        $mock->shouldReceive('projects')->andReturn(['my-project' => '/path/to/project']);
    });

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists')->once()->with('my-project', 'scratch');
        $mock->shouldReceive('contents')->once()->with('my-project', 'scratch')->andReturn("echo 'Hello, world!';");
    });

    $this->get('/')
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Snippets/Run')
                ->where('snippetName', 'scratch')
                ->where('content', "echo 'Hello, world!';")
                ->where('currentProject', 'my-project'),
        );
});

it('opens the named snippet from a project in the URL', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('currentProject')->never();
        $mock->shouldReceive('projectPath')->with('my-project')->andReturn('/path/to/project');
        $mock->shouldReceive('phpBinary')->with('my-project')->andReturn('/path/to/php');
        $mock->shouldReceive('phpVersion')->andReturn('8.5.0');
        $mock->shouldReceive('laravelVersion')->andReturn('13.0.0');
        $mock->shouldReceive('projects')->andReturn(['my-project' => '/path/to/project']);
    });

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists')->once()->with('my-project', 'my-snippet');
        $mock->shouldReceive('contents')->once()->with('my-project', 'my-snippet')->andReturn('echo "existing";');
    });

    $this->get('/my-project/my-snippet')
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('snippetName', 'my-snippet')
                ->where('content', 'echo "existing";')
                ->where('currentProject', 'my-project'),
        );
});

it('shows the php and laravel version of the current project', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('currentProject')->once()->andReturn('my-project');
        $mock->shouldReceive('projectPath')->with('my-project')->andReturn('/path/to/project');
        $mock->shouldReceive('phpBinary')->with('my-project')->andReturn('/path/to/php');
        $mock->shouldReceive('phpVersion')->with('/path/to/php')->andReturn('8.5.0');
        $mock->shouldReceive('laravelVersion')->with('/path/to/php', '/path/to/project')->andReturn('13.0.0');
        $mock->shouldReceive('projects')->andReturn(['my-project' => '/path/to/project']);
    });

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists');
        $mock->shouldReceive('contents')->andReturn('');
    });

    $this->get('/')
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('phpVersion', '8.5.0')
                ->where('laravelVersion', '13.0.0'),
        );
});

it('lists every herd project so the palette can switch between them', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('currentProject')->once()->andReturn('my-project');
        $mock->shouldReceive('projectPath')->with('my-project')->andReturn('/path/to/project');
        $mock->shouldReceive('phpBinary')->andReturn('/path/to/php');
        $mock->shouldReceive('phpVersion')->andReturn('8.5.0');
        $mock->shouldReceive('laravelVersion')->andReturn('13.0.0');
        $mock->shouldReceive('projects')->andReturn(['my-project' => '/path/to/project', 'other' => '/path/to/other']);
    });

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists');
        $mock->shouldReceive('contents')->andReturn('');
    });

    $this->get('/')
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('projects', ['my-project', 'other']),
        );
});

it('throws when the project in the url is unknown to herd', function (): void {
    $this->withoutExceptionHandling();

    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('currentProject')->never();
        $mock->shouldReceive('projectPath')->with('unknown-project')->once()->andReturn(null);
    });

    $this->get('/unknown-project/scratch');
})->throws(RuntimeException::class, 'Unknown Herd project: unknown-project');
