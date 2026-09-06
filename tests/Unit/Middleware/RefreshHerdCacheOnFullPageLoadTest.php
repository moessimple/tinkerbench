<?php

declare(strict_types=1);

use App\Http\Middleware\RefreshHerdCacheOnFullPageLoad;
use App\Support\Herd;
use Illuminate\Support\Facades\Route;

it('re-pulls a fresh herd snapshot for the requested project on a full page load', function (): void {
    $this->mock(Herd::class)
        ->shouldReceive('snapshotProject')->once()->with('known-project', true)->andReturnNull();

    Route::get('api/testing/project-under-test/{project?}', fn (?string $project = null): string => $project ?? 'none')
        ->middleware(RefreshHerdCacheOnFullPageLoad::class);

    $this->get('api/testing/project-under-test/known-project')
        ->assertOk()
        ->assertSee('known-project');
});

it('re-pulls a fresh herd snapshot for the current project when the url names none', function (): void {
    $this->mock(Herd::class)
        ->shouldReceive('snapshotProject')->once()->with(null, true)->andReturnNull();

    Route::get('api/testing/project-under-test/{project?}', fn (?string $project = null): string => $project ?? 'none')
        ->middleware(RefreshHerdCacheOnFullPageLoad::class);

    $this->get('api/testing/project-under-test')
        ->assertOk()
        ->assertSee('none');
});

it('skips the refresh entirely for inertia navigation requests', function (): void {
    $this->mock(Herd::class)
        ->shouldReceive('snapshotProject')->never();

    Route::get('api/testing/project-under-test/{project?}', fn (?string $project = null): string => $project ?? 'none')
        ->middleware(RefreshHerdCacheOnFullPageLoad::class);

    $this->withHeaders(['X-Inertia' => 'true'])
        ->get('api/testing/project-under-test/known-project')
        ->assertOk()
        ->assertSee('known-project');
});
