<?php

declare(strict_types=1);

use Support\Herd;

it('returns the project names from herd', function (): void {
    $this->mock(Herd::class)
        ->shouldReceive('projectNames')->once()->andReturn(['apple', 'zebra']);

    $this->getJson('/api/projects')
        ->assertOk()
        ->assertExactJson(['apple', 'zebra']);
});
