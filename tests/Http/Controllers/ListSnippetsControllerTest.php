<?php

declare(strict_types=1);

use App\Http\Controllers\ListSnippetsController;
use App\Http\Middleware\EnsureKnownProject;
use App\Support\Herd;
use App\Support\SnippetRepository;
use Mockery\MockInterface;

it('uses the right middleware', function (): void {
    expect(ListSnippetsController::class)->toUseMiddleware(EnsureKnownProject::class);
});

it('returns the snippet names from the repository', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)
        ->shouldReceive('names')->once()->with('my-project')->andReturn(['apple', 'zebra']);

    $this->getJson('/api/projects/my-project/snippets')
        ->assertOk()
        ->assertExactJson(['apple', 'zebra']);
});

it('rejects a project unknown to herd with a 404', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPath')->with('does-not-exist')->once()->andReturn(null);
    });

    $this->mock(SnippetRepository::class)
        ->shouldReceive('names')->never();

    $this->getJson('/api/projects/does-not-exist/snippets')
        ->assertNotFound()
        ->assertJsonPath('message', 'Unknown Herd project: does-not-exist');
});
