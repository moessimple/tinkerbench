<?php

declare(strict_types=1);

use App\Http\Controllers\ListSnippetsController;
use App\Http\Middleware\EnsureKnownProject;
use App\Support\SnippetRepository;

it('uses the right middleware', function (): void {
    expect(ListSnippetsController::class)->toUseMiddleware(EnsureKnownProject::class);
});

it('uses the right repository', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)
        ->shouldReceive('names')->once()->with('my-project')->andReturn([]);

    $this->getJson('/api/projects/my-project/snippets');
});

it('returns the snippet names from the repository', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)->shouldReceive('names')->andReturn(['apple', 'zebra']);

    $this->getJson('/api/projects/my-project/snippets')
        ->assertOk()
        ->assertExactJson(['apple', 'zebra']);
});
