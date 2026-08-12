<?php

declare(strict_types=1);

use Application\Snippets\Controllers\UpdateSnippetNameController;
use Application\Snippets\Requests\SnippetNameRequest;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Support\Enums\RenameSnippetResult;
use Support\SnippetRepository;

it('uses the right request', function (): void {
    expect(UpdateSnippetNameController::class)->toUseFormRequest(SnippetNameRequest::class);
});

it('uses the right middleware', function (): void {
    expect(UpdateSnippetNameController::class)->toUseMiddleware(HandlePrecognitiveRequests::class);
});

it('renames the snippet via the repository', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('rename')->once()->with('old', 'new')->andReturn(RenameSnippetResult::Renamed);

    $this->patchJson('/api/snippets/old', ['name' => 'new'])
        ->assertOk()
        ->assertExactJson(['ok' => true]);
});

it('returns 404 when the repository reports the snippet is missing', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('rename')->once()->with('missing', 'new')->andReturn(RenameSnippetResult::Missing);

    $this->patchJson('/api/snippets/missing', ['name' => 'new'])
        ->assertNotFound()
        ->assertExactJson(['ok' => false, 'error' => 'Snippet not found']);
});

it('returns 409 when the repository reports a name conflict', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('rename')->once()->with('old', 'new')->andReturn(RenameSnippetResult::Conflict);

    $this->patchJson('/api/snippets/old', ['name' => 'new'])
        ->assertStatus(409)
        ->assertExactJson(['ok' => false, 'error' => 'A snippet named "new" already exists']);
});
