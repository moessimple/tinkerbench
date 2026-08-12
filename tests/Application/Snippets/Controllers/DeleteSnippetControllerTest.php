<?php

declare(strict_types=1);

use Support\SnippetRepository;

it('deletes the snippet via the repository', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('delete')->once()->with('scratch')->andReturn(true);

    $this->deleteJson('/api/snippets/scratch')
        ->assertOk()
        ->assertExactJson(['ok' => true]);
});

it('returns 404 when the repository reports the snippet is missing', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('delete')->once()->with('missing')->andReturn(false);

    $this->deleteJson('/api/snippets/missing')
        ->assertNotFound()
        ->assertExactJson(['ok' => false, 'error' => 'Snippet not found']);
});
