<?php

declare(strict_types=1);

use App\Http\Controllers\UpdateSnippetContentController;
use App\Http\Middleware\EnsureKnownProject;
use App\Http\Requests\UpdateSnippetContentRequest;
use App\Support\SnippetRepository;

it('uses the right request', function (): void {
    expect(UpdateSnippetContentController::class)->toUseFormRequest(UpdateSnippetContentRequest::class);
});

it('uses the right middleware', function (): void {
    expect(UpdateSnippetContentController::class)->toUseMiddleware(EnsureKnownProject::class);
});

it('uses the right repository', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)
        ->shouldReceive('write')->once()->with('my-project', 'scratch', 'echo "saved";')->andReturn(true);

    $this->putJson('/api/projects/my-project/snippets/scratch', ['content' => 'echo "saved";']);
});

it('saves the content via the repository', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)->shouldReceive('write')->andReturn(true);

    $this->putJson('/api/projects/my-project/snippets/scratch', ['content' => 'echo "saved";'])
        ->assertNoContent();
});

it('reports a server error as JSON when the repository fails to write', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)->shouldReceive('write')->andReturn(false);

    $this->putJson('/api/projects/my-project/snippets/scratch', ['content' => 'echo "saved";'])
        ->assertServerError()
        ->assertJsonPath('message', 'Unable to save the snippet.');
});
