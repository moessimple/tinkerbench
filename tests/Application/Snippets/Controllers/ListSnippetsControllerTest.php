<?php

declare(strict_types=1);

use Support\SnippetRepository;

it('returns the snippet names from the repository', function (): void {
    $this->mock(SnippetRepository::class)
        ->shouldReceive('names')->once()->andReturn(['apple', 'zebra']);

    $this->getJson('/api/snippets')
        ->assertOk()
        ->assertExactJson(['apple', 'zebra']);
});
