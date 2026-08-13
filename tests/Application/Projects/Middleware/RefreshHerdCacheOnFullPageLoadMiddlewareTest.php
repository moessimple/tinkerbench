<?php

declare(strict_types=1);

use Application\Projects\Middleware\RefreshHerdCacheOnFullPageLoadMiddleware;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use Support\Herd;

it("refreshes the project cache and the requested project's php binary on a full page load", function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->once()->andReturn(['known-project' => '/path/to/known-project']);
        $mock->shouldReceive('refreshPhpBinary')->once()->with('known-project');
        $mock->shouldReceive('currentProject')->never();
    });

    Route::get('api/testing/project-under-test/{project?}', fn (?string $project = null): string => $project ?? 'none')
        ->middleware(RefreshHerdCacheOnFullPageLoadMiddleware::class);

    $this->get('api/testing/project-under-test/known-project')
        ->assertOk()
        ->assertSee('known-project');
});

it('resolves and refreshes the current project when the url names none', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->once()->andReturn(['current-project' => '/path/to/current-project']);
        $mock->shouldReceive('currentProject')->once()->andReturn('current-project');
        $mock->shouldReceive('refreshPhpBinary')->once()->with('current-project');
    });

    Route::get('api/testing/project-under-test/{project?}', fn (?string $project = null): string => $project ?? 'none')
        ->middleware(RefreshHerdCacheOnFullPageLoadMiddleware::class);

    $this->get('api/testing/project-under-test')
        ->assertOk()
        ->assertSee('none');
});

it('does not refresh a php binary for a project unknown to herd', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->once()->andReturn([]);
        $mock->shouldReceive('refreshPhpBinary')->never();
        $mock->shouldReceive('currentProject')->never();
    });

    Route::get('api/testing/project-under-test/{project?}', fn (?string $project = null): string => $project ?? 'none')
        ->middleware(RefreshHerdCacheOnFullPageLoadMiddleware::class);

    $this->get('api/testing/project-under-test/unknown-project')
        ->assertOk()
        ->assertSee('unknown-project');
});

it('skips the refresh entirely for inertia navigation requests', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->never();
        $mock->shouldReceive('refreshPhpBinary')->never();
        $mock->shouldReceive('currentProject')->never();
    });

    Route::get('api/testing/project-under-test/{project?}', fn (?string $project = null): string => $project ?? 'none')
        ->middleware(RefreshHerdCacheOnFullPageLoadMiddleware::class);

    $this->withHeaders(['X-Inertia' => 'true'])
        ->get('api/testing/project-under-test/known-project')
        ->assertOk()
        ->assertSee('known-project');
});
