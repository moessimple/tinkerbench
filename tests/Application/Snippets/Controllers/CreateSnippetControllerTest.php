<?php

declare(strict_types=1);

use Application\Snippets\Controllers\CreateSnippetController;
use Application\Snippets\Requests\SnippetNameRequest;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Support\SnippetRepository;

it('uses the right request', function (): void {
    expect(CreateSnippetController::class)->toUseFormRequest(SnippetNameRequest::class);
});

it('uses the right middleware', function (): void {
    expect(CreateSnippetController::class)->toUseMiddleware(HandlePrecognitiveRequests::class);
});

it('creates the snippet via the repository', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('ensureExists')->once()->with('my-new-snippet');

    $this->postJson('/api/snippets', ['name' => 'my-new-snippet'])
        ->assertOk()
        ->assertExactJson(['ok' => true]);
});
