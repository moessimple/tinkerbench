<?php

declare(strict_types=1);

use App\Enums\CreateSnippetResult;
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
        ->shouldReceive('create')->once()->with('my-project', 'my-new-snippet')->andReturn(CreateSnippetResult::Created);

    $this->postJson('/api/projects/my-project/snippets', ['name' => 'my-new-snippet']);
});

it('creates the snippet via the repository', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)->shouldReceive('create')->andReturn(CreateSnippetResult::Created);

    $this->postJson('/api/projects/my-project/snippets', ['name' => 'my-new-snippet'])
        ->assertNoContent();
});

it('returns 409 when the repository reports the name is already taken', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)->shouldReceive('create')->andReturn(CreateSnippetResult::Conflict);

    $this->postJson('/api/projects/my-project/snippets', ['name' => 'my-new-snippet'])
        ->assertStatus(409)
        ->assertJsonPath('message', "A snippet named 'my-new-snippet' already exists");
});

it('reports a server error as JSON when the repository fails to create the snippet', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class)->shouldReceive('create')->andReturn(CreateSnippetResult::Failed);

    $this->postJson('/api/projects/my-project/snippets', ['name' => 'my-new-snippet'])
        ->assertServerError()
        ->assertJsonPath('message', 'Unable to create the snippet.');
});
