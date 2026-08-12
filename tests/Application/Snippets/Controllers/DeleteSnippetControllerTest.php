<?php

declare(strict_types=1);

use Support\SnippetRepository;

it('deletes the snippet via the repository', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('delete')->once()->with('my-project', 'scratch')->andReturn(true);

    $this->deleteJson('/api/projects/my-project/snippets/scratch')
        ->assertOk()
        ->assertExactJson(['ok' => true]);
});

it('returns 404 when the repository reports the snippet is missing', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('delete')->once()->with('my-project', 'missing')->andReturn(false);

    $this->deleteJson('/api/projects/my-project/snippets/missing')
        ->assertNotFound()
        ->assertExactJson(['ok' => false, 'error' => 'Snippet not found']);
});
