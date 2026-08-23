<?php

declare(strict_types=1);

use App\Enums\DeleteSnippetResult;
use App\Http\Controllers\DeleteSnippetController;
use App\Http\Middleware\EnsureKnownProject;
use App\Support\SnippetRepository;

beforeEach(function (): void {
    mockKnownProject();
});

it('uses the right middleware', function (): void {
    expect(DeleteSnippetController::class)->toUseMiddleware(EnsureKnownProject::class);
});

it('uses the right repository', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('delete')->once()->with('my-project', 'scratch')->andReturn(DeleteSnippetResult::Deleted);

    $this->deleteJson('/api/projects/my-project/snippets/scratch');
});

it('deletes the snippet via the repository', function (): void {
    $this->mock(SnippetRepository::class)->shouldReceive('delete')->andReturn(DeleteSnippetResult::Deleted);

    $this->deleteJson('/api/projects/my-project/snippets/scratch')
        ->assertNoContent();
});

it('returns 404 when the repository reports the snippet is missing', function (): void {
    $this->mock(SnippetRepository::class)->shouldReceive('delete')->andReturn(DeleteSnippetResult::Missing);

    $this->deleteJson('/api/projects/my-project/snippets/missing')
        ->assertNotFound()
        ->assertJsonPath('message', 'Snippet not found');
});

it('reports a server error as JSON when the repository fails to delete', function (): void {
    $this->mock(SnippetRepository::class)->shouldReceive('delete')->andReturn(DeleteSnippetResult::Failed);

    $this->deleteJson('/api/projects/my-project/snippets/scratch')
        ->assertServerError()
        ->assertJsonPath('message', 'Unable to delete the snippet.');
});
