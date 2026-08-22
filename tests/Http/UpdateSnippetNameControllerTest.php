<?php

declare(strict_types=1);

use App\Enums\RenameSnippetResult;
use App\Http\Controllers\UpdateSnippetNameController;
use App\Http\Middleware\EnsureKnownProject;
use App\Http\Requests\SnippetNameRequest;
use App\Support\SnippetRepository;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;

beforeEach(function (): void {
    mockKnownProject();
});

it('uses the right request', function (): void {
    expect(UpdateSnippetNameController::class)->toUseFormRequest(SnippetNameRequest::class);
});

it('uses the right middleware', function (): void {
    expect(UpdateSnippetNameController::class)
        ->toUseMiddleware(HandlePrecognitiveRequests::class)
        ->toUseMiddleware(EnsureKnownProject::class);
});

it('uses the right repository', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('rename')->once()->with('my-project', 'old', 'new')->andReturn(RenameSnippetResult::Renamed);

    $this->patchJson('/api/projects/my-project/snippets/old', ['name' => 'new']);
});

it('renames the snippet via the repository', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('rename')->andReturn(RenameSnippetResult::Renamed);

    $this->patchJson('/api/projects/my-project/snippets/old', ['name' => 'new'])
        ->assertNoContent();
});

it('returns 404 when the repository reports the snippet is missing', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('rename')->andReturn(RenameSnippetResult::Missing);

    $this->patchJson('/api/projects/my-project/snippets/missing', ['name' => 'new'])
        ->assertNotFound()
        ->assertJsonPath('message', 'Snippet not found');
});

it('returns 409 when the repository reports a name conflict', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('rename')->andReturn(RenameSnippetResult::Conflict);

    $this->patchJson('/api/projects/my-project/snippets/old', ['name' => 'new'])
        ->assertStatus(409)
        ->assertJsonPath('message', "A snippet named 'new' already exists");
});

it('reports a server error as JSON when the repository fails to rename', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('rename')->andReturn(RenameSnippetResult::Failed);

    $this->patchJson('/api/projects/my-project/snippets/old', ['name' => 'new'])
        ->assertServerError()
        ->assertJsonPath('message', 'Unable to rename the snippet.');
});
