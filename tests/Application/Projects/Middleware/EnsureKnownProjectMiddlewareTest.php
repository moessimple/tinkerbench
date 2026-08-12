<?php

declare(strict_types=1);

use Application\Projects\Middleware\EnsureKnownProjectMiddleware;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use Support\Herd;

it('lets a known project through', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPath')->once()->with('known-project')->andReturn('/path/to/known-project');
    });

    Route::get('api/project-under-test/{project}', fn (string $project): string => $project)
        ->middleware(EnsureKnownProjectMiddleware::class);

    $this->get('api/project-under-test/known-project')
        ->assertOk()
        ->assertSee('known-project');
});

it('rejects an unknown project with a 404', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPath')->once()->with('unknown-project')->andReturn(null);
    });

    Route::get('api/project-under-test/{project}', fn (string $project): string => $project)
        ->middleware(EnsureKnownProjectMiddleware::class);

    $this->getJson('api/project-under-test/unknown-project')
        ->assertNotFound()
        ->assertJsonPath('message', 'Unknown Herd project: unknown-project');
});
