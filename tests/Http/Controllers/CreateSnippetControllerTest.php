<?php

declare(strict_types=1);

use App\Http\Controllers\CreateSnippetController;
use App\Http\Middleware\EnsureKnownProjectMiddleware;
use App\Http\Requests\SnippetNameRequest;
use App\Support\SnippetRepository;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;

it('uses the right request', function (): void {
    expect(CreateSnippetController::class)->toUseFormRequest(SnippetNameRequest::class);
});

it('uses the right middleware', function (): void {
    expect(CreateSnippetController::class)
        ->toUseMiddleware(HandlePrecognitiveRequests::class)
        ->toUseMiddleware(EnsureKnownProjectMiddleware::class);
});

it('creates the snippet via the repository', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)
        ->shouldReceive('ensureExists')->once()->with('my-project', 'my-new-snippet');

    $this->postJson('/api/projects/my-project/snippets', ['name' => 'my-new-snippet'])
        ->assertOk()
        ->assertExactJson(['ok' => true]);
});
