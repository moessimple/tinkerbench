<?php

declare(strict_types=1);

use Support\SnippetRepository;

it('returns the snippet names from the repository', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('names')->once()->with('my-project')->andReturn(['apple', 'zebra']);

    $this->getJson('/api/projects/my-project/snippets')
        ->assertOk()
        ->assertExactJson(['apple', 'zebra']);
});
