<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Inertia\Testing\AssertableInertia;
use Mockery\MockInterface;
use Support\Herd;
use Support\SnippetRepository;

it('opens the default scratch snippet for the current project', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->once()->andReturn(['my-project' => '/path/to/project']);
        $mock->shouldReceive('currentProject')->twice()->andReturn('my-project');
        $mock->shouldReceive('projectPath')->with('my-project')->andReturn('/path/to/project');
        $mock->shouldReceive('refreshPhpBinary')->with('my-project')->andReturn('/path/to/php');
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
                ->component('Snippets/OpenSnippet')
                ->where('snippetName', 'scratch')
                ->where('content', "echo 'Hello, world!';")
                ->where('currentProject', 'my-project'),
        );
});

it('opens the named snippet from a project in the URL', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->once()->andReturn(['my-project' => '/path/to/project']);
        $mock->shouldReceive('currentProject')->never();
        $mock->shouldReceive('projectPath')->with('my-project')->andReturn('/path/to/project');
        $mock->shouldReceive('refreshPhpBinary')->with('my-project')->andReturn('/path/to/php');
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

it('uses cached herd data for inertia navigation', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->never();
        $mock->shouldReceive('refreshPhpBinary')->never();
        $mock->shouldReceive('currentProject')->never();
        $mock->shouldReceive('projectPath')->with('my-project')->andReturn('/path/to/project');
        $mock->shouldReceive('phpBinary')->with('my-project')->andReturn('/path/to/php');
        $mock->shouldReceive('phpVersion')->andReturn('8.5.0');
        $mock->shouldReceive('laravelVersion')->andReturn('13.0.0');
        $mock->shouldReceive('projects')->andReturn(['my-project' => '/path/to/project']);
    });

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists')->once()->with('my-project', 'scratch');
        $mock->shouldReceive('contents')->once()->with('my-project', 'scratch')->andReturn('');
    });

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => resolve(HandleInertiaRequests::class)->version(request()),
    ])
        ->get('/my-project/scratch')
        ->assertOk()
        ->assertJsonPath('component', 'Snippets/OpenSnippet')
        ->assertJsonPath('props.currentProject', 'my-project');
});

it('shows the php and laravel version of the current project', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->once()->andReturn(['my-project' => '/path/to/project']);
        $mock->shouldReceive('currentProject')->twice()->andReturn('my-project');
        $mock->shouldReceive('projectPath')->with('my-project')->andReturn('/path/to/project');
        $mock->shouldReceive('refreshPhpBinary')->with('my-project')->andReturn('/path/to/php');
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

it('rejects a two-segment url naming a project unknown to herd with a 404', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->once()->andReturn([]);
        $mock->shouldReceive('projectPath')->with('unknown-project')->once()->andReturn(null);
        $mock->shouldReceive('currentProject')->never();
    });

    $this->mock(SnippetRepository::class)
        ->shouldReceive('ensureExists')->never();

    $this->get('/unknown-project/scratch')->assertNotFound();
});

it('rejects a single unknown url segment with a 404', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->once()->andReturn([]);
        $mock->shouldReceive('projectPath')->with('scratch')->once()->andReturn(null);
        $mock->shouldReceive('currentProject')->never();
    });

    $this->mock(SnippetRepository::class)
        ->shouldReceive('ensureExists')->never();

    $this->get('/scratch')->assertNotFound();
});

it('opens a single url segment as a project switch when it is a known project', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->once()->andReturn(['other-project' => '/path/to/other-project']);
        $mock->shouldReceive('projectPath')->with('other-project')->once()->andReturn('/path/to/other-project');
        $mock->shouldReceive('currentProject')->never();
        $mock->shouldReceive('refreshPhpBinary')->with('other-project')->andReturn('/path/to/php');
        $mock->shouldReceive('phpBinary')->with('other-project')->andReturn('/path/to/php');
        $mock->shouldReceive('phpVersion')->andReturn('8.5.0');
        $mock->shouldReceive('laravelVersion')->andReturn('13.0.0');
    });

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists')->once()->with('other-project', 'scratch');
        $mock->shouldReceive('contents')->once()->with('other-project', 'scratch')->andReturn('');
    });

    $this->get('/other-project')
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('snippetName', 'scratch')
                ->where('currentProject', 'other-project'),
        );
});
