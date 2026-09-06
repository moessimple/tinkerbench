<?php

declare(strict_types=1);

use App\Http\Controllers\OpenSnippetController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RefreshHerdCacheOnFullPageLoad;
use App\Support\Herd;
use App\Support\SnippetRepository;
use Inertia\Testing\AssertableInertia;
use Mockery\MockInterface;

it('uses the right middleware', function (): void {
    expect(OpenSnippetController::class)->toUseMiddleware(RefreshHerdCacheOnFullPageLoad::class);
});

it('reads the herd snapshot for the resolved project', function (): void {
    $herd = $this->mock(Herd::class);
    $herd->shouldReceive('snapshotProject')->with('my-project', true)->once()->andReturn(projectSnapshot());
    $herd->shouldReceive('snapshotProject')->with('my-project')->once()->andReturn(projectSnapshot());

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists')->andReturn(true);
        $mock->shouldReceive('contents')->andReturn('');
    });

    $this->get('/my-project/scratch')->assertOk();
});

it('opens the default scratch snippet for the current project', function (): void {
    $this->mock(Herd::class)
        ->shouldReceive('snapshotProject')->andReturn(projectSnapshot('my-project'));

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists')->once()->with('my-project', 'scratch')->andReturn(true);
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
    $this->mock(Herd::class)
        ->shouldReceive('snapshotProject')->andReturn(projectSnapshot('my-project'));

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists')->once()->with('my-project', 'my-snippet')->andReturn(true);
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

it('opens a single url segment as a project switch when it is a known project', function (): void {
    $this->mock(Herd::class)
        ->shouldReceive('snapshotProject')->andReturn(projectSnapshot('other-project'));

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists')->once()->with('other-project', 'scratch')->andReturn(true);
        $mock->shouldReceive('contents')->once()->with('other-project', 'scratch')->andReturn('');
    });

    $this->get('/other-project')
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('snippetName', 'scratch')
                ->where('currentProject', 'other-project'),
        );
});

it('shows the php and laravel version from the snapshot', function (): void {
    $this->mock(Herd::class)->shouldReceive('snapshotProject')->andReturn(projectSnapshot());

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists')->andReturn(true);
        $mock->shouldReceive('contents')->andReturn('');
    });

    $this->get('/')
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('phpVersion', '8.5.0')
                ->where('laravelVersion', '13.0.0'),
        );
});

it('reports a server error as JSON when the repository fails to create the default snippet', function (): void {
    $this->mock(Herd::class)->shouldReceive('snapshotProject')->andReturn(projectSnapshot());

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists')->once()->with('my-project', 'scratch')->andReturn(false);
        $mock->shouldReceive('contents')->never();
    });

    $this->getJson('/')
        ->assertServerError()
        ->assertJsonPath('message', 'Unable to create the snippet.');
});

it('rejects a url whose project is not a known herd site with a 404', function (string $url): void {
    $this->mock(Herd::class)->shouldReceive('snapshotProject')->andReturnNull();
    $this->mock(SnippetRepository::class)->shouldReceive('ensureExists')->never();

    $this->get($url)->assertNotFound();
})->with([
    'two segments' => '/unknown-project/scratch',
    'one segment' => '/unknown-segment',
]);

it('reads the snapshot without a refresh for an inertia navigation', function (): void {
    $herd = $this->mock(Herd::class);
    $herd->shouldReceive('snapshotProject')->with('my-project', true)->never();
    $herd->shouldReceive('snapshotProject')->with('my-project')->once()->andReturn(projectSnapshot());

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('ensureExists')->once()->with('my-project', 'scratch')->andReturn(true);
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
