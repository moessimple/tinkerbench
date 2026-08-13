<?php

declare(strict_types=1);

use Application\Projects\Middleware\EnsureKnownProjectMiddleware;
use Application\Snippets\Controllers\UpdateSnippetContentController;
use Application\Snippets\Requests\UpdateSnippetContentRequest;
use Support\SnippetRepository;

it('uses the right request', function (): void {
    expect(UpdateSnippetContentController::class)->toUseFormRequest(UpdateSnippetContentRequest::class);
});

it('uses the right middleware', function (): void {
    expect(UpdateSnippetContentController::class)->toUseMiddleware(EnsureKnownProjectMiddleware::class);
});

it('saves the content via the repository', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)
        ->shouldReceive('write')->once()->with('my-project', 'scratch', 'echo "saved";');

    $this->putJson('/api/projects/my-project/snippets/scratch', ['content' => 'echo "saved";'])
        ->assertOk()
        ->assertExactJson(['ok' => true]);
});
