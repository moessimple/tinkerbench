<?php

declare(strict_types=1);

use App\Support\Herd;

it('uses herd to list the projects', function (): void {
    $this->mock(Herd::class)->shouldReceive('projectNames')->once()->andReturn([]);

    $this->getJson('/api/projects');
});

it('returns the project names from herd', function (): void {
    $this->mock(Herd::class)->shouldReceive('projectNames')->andReturn(['apple', 'zebra']);

    $this->getJson('/api/projects')
        ->assertOk()
        ->assertExactJson(['apple', 'zebra']);
});
