<?php

declare(strict_types=1);

use App\Http\Controllers\UpdateSnippetContentController;
use App\Http\Middleware\EnsureKnownProject;
use App\Http\Requests\UpdateSnippetContentRequest;
use App\Support\SnippetRepository;
use Mockery\MockInterface;

it('uses the right request', function (): void {
    expect(UpdateSnippetContentController::class)->toUseFormRequest(UpdateSnippetContentRequest::class);
});

it('uses the right middleware', function (): void {
    expect(UpdateSnippetContentController::class)->toUseMiddleware(EnsureKnownProject::class);
});

it('uses the right repository', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('exists')->once()->with('my-project', 'scratch')->andReturn(true);
        $mock->shouldReceive('write')->once()->with('my-project', 'scratch', 'echo "saved";')->andReturn(true);
    });

    $this->putJson('/api/projects/my-project/snippets/scratch', ['content' => 'echo "saved";']);
});

it('saves the content via the repository', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('exists')->andReturn(true);
        $mock->shouldReceive('write')->andReturn(true);
    });

    $this->putJson('/api/projects/my-project/snippets/scratch', ['content' => 'echo "saved";'])
        ->assertNoContent();
});

it('returns 404 without writing when the snippet no longer exists', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('exists')->andReturn(false);
        $mock->shouldReceive('write')->never();
    });

    $this->putJson('/api/projects/my-project/snippets/scratch', ['content' => 'echo "saved";'])
        ->assertNotFound()
        ->assertJsonPath('message', 'Snippet not found');
});

it('reports a server error as JSON when the repository fails to write', function (): void {
    mockKnownProject();

    $this->mock(SnippetRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('exists')->andReturn(true);
        $mock->shouldReceive('write')->andReturn(false);
    });

    $this->putJson('/api/projects/my-project/snippets/scratch', ['content' => 'echo "saved";'])
        ->assertServerError()
        ->assertJsonPath('message', 'Unable to save the snippet.');
});

it('does not route a snippet segment that carries disallowed characters', function (): void {
    $this->mock(SnippetRepository::class);

    $this->putJson('/api/projects/my-project/snippets/bad.name', ['content' => 'echo 1;'])
        ->assertNotFound();
});
