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
        ->shouldReceive('write')->once()->with('my-project', 'scratch', 'echo "saved";');

    $this->putJson('/api/projects/my-project/snippets/scratch', ['content' => 'echo "saved";']);
});

it('saves the content via the repository', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)->shouldReceive('write');

    $this->putJson('/api/projects/my-project/snippets/scratch', ['content' => 'echo "saved";'])
        ->assertNoContent();
});
