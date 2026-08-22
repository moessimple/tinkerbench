<?php

declare(strict_types=1);

use App\Http\Controllers\CreateSnippetController;
use App\Http\Middleware\EnsureKnownProject;
use App\Http\Requests\SnippetNameRequest;
use App\Support\SnippetRepository;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;

it('uses the right request', function (): void {
    expect(CreateSnippetController::class)->toUseFormRequest(SnippetNameRequest::class);
});

it('uses the right middleware', function (): void {
    expect(CreateSnippetController::class)
        ->toUseMiddleware(HandlePrecognitiveRequests::class)
        ->toUseMiddleware(EnsureKnownProject::class);
});

it('uses the right repository', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)
        ->shouldReceive('ensureExists')->once()->with('my-project', 'my-new-snippet')->andReturn(true);

    $this->postJson('/api/projects/my-project/snippets', ['name' => 'my-new-snippet']);
});

it('creates the snippet via the repository', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)->shouldReceive('ensureExists')->andReturn(true);

    $this->postJson('/api/projects/my-project/snippets', ['name' => 'my-new-snippet'])
        ->assertNoContent();
});

it('reports a server error as JSON when the repository fails to create the snippet', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)->shouldReceive('ensureExists')->andReturn(false);

    $this->postJson('/api/projects/my-project/snippets', ['name' => 'my-new-snippet'])
        ->assertServerError()
        ->assertJsonPath('message', 'Unable to create the snippet.');
});
