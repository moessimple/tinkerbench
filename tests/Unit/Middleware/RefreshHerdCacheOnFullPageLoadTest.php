<?php

declare(strict_types=1);

use App\Http\Middleware\RefreshHerdCacheOnFullPageLoad;
use App\Support\Herd;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;

it("refreshes the project cache and the requested project's herd data on a full page load", function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->once()->andReturn(['known-project' => '/path/to/known-project']);
        $mock->shouldReceive('resolveProject')->once()->with('known-project')->andReturn('known-project');
        $mock->shouldReceive('refreshPhpBinary')->once()->with('known-project')->andReturn('/path/to/php');
        $mock->shouldReceive('refreshPhpVersion')->once()->with('/path/to/php');
        $mock->shouldReceive('projectPath')->once()->with('known-project')->andReturn('/path/to/known-project');
        $mock->shouldReceive('refreshLaravelVersion')->once()->with('/path/to/php', '/path/to/known-project');
    });

    Route::get('api/testing/project-under-test/{project?}', fn (?string $project = null): string => $project ?? 'none')
        ->middleware(RefreshHerdCacheOnFullPageLoad::class);

    $this->get('api/testing/project-under-test/known-project')
        ->assertOk()
        ->assertSee('known-project');
});

it('resolves and refreshes the current project when the url names none', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->once()->andReturn(['current-project' => '/path/to/current-project']);
        $mock->shouldReceive('resolveProject')->once()->with(null)->andReturn('current-project');
        $mock->shouldReceive('refreshPhpBinary')->once()->with('current-project')->andReturn('/path/to/php');
        $mock->shouldReceive('refreshPhpVersion')->once()->with('/path/to/php');
        $mock->shouldReceive('projectPath')->once()->with('current-project')->andReturn('/path/to/current-project');
        $mock->shouldReceive('refreshLaravelVersion')->once()->with('/path/to/php', '/path/to/current-project');
    });

    Route::get('api/testing/project-under-test/{project?}', fn (?string $project = null): string => $project ?? 'none')
        ->middleware(RefreshHerdCacheOnFullPageLoad::class);

    $this->get('api/testing/project-under-test')
        ->assertOk()
        ->assertSee('none');
});

it('does not refresh any herd data for a project unknown to herd', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->once()->andReturn([]);
        $mock->shouldReceive('resolveProject')->once()->with('unknown-project')->andReturn('unknown-project');
        $mock->shouldReceive('refreshPhpBinary')->never();
        $mock->shouldReceive('refreshPhpVersion')->never();
        $mock->shouldReceive('refreshLaravelVersion')->never();
    });

    Route::get('api/testing/project-under-test/{project?}', fn (?string $project = null): string => $project ?? 'none')
        ->middleware(RefreshHerdCacheOnFullPageLoad::class);

    $this->get('api/testing/project-under-test/unknown-project')
        ->assertOk()
        ->assertSee('unknown-project');
});

it('skips the laravel version refresh when the project path cannot be resolved', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->once()->andReturn(['known-project' => '/path/to/known-project']);
        $mock->shouldReceive('resolveProject')->once()->with('known-project')->andReturn('known-project');
        $mock->shouldReceive('refreshPhpBinary')->once()->with('known-project')->andReturn('/path/to/php');
        $mock->shouldReceive('refreshPhpVersion')->once()->with('/path/to/php');
        $mock->shouldReceive('projectPath')->once()->with('known-project')->andReturn(null);
        $mock->shouldReceive('refreshLaravelVersion')->never();
    });

    Route::get('api/testing/project-under-test/{project?}', fn (?string $project = null): string => $project ?? 'none')
        ->middleware(RefreshHerdCacheOnFullPageLoad::class);

    $this->get('api/testing/project-under-test/known-project')->assertOk();
});

it('skips the refresh entirely for inertia navigation requests', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('refreshProjects')->never();
        $mock->shouldReceive('refreshPhpBinary')->never();
        $mock->shouldReceive('refreshPhpVersion')->never();
        $mock->shouldReceive('refreshLaravelVersion')->never();
        $mock->shouldReceive('resolveProject')->never();
    });

    Route::get('api/testing/project-under-test/{project?}', fn (?string $project = null): string => $project ?? 'none')
        ->middleware(RefreshHerdCacheOnFullPageLoad::class);

    $this->withHeaders(['X-Inertia' => 'true'])
        ->get('api/testing/project-under-test/known-project')
        ->assertOk()
        ->assertSee('known-project');
});
