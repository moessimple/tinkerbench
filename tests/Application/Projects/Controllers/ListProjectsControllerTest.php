<?php

declare(strict_types=1);

use Support\Herd;

it('returns the project names from herd', function (): void {
    $this->mock(Herd::class)
        ->shouldReceive('projects')->once()->andReturn(['apple' => '/path/to/apple', 'zebra' => '/path/to/zebra']);

    $this->getJson('/api/projects')
        ->assertOk()
        ->assertExactJson(['apple', 'zebra']);
});
