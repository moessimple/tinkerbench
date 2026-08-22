<?php

declare(strict_types=1);

use App\Support\Herd;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\FakeProcessResult;

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

it('reports a friendly message instead of a raw process error when a herd command fails', function (): void {
    $this->mock(Herd::class)
        ->shouldReceive('projectNames')
        ->andThrow(new ProcessFailedException(new FakeProcessResult(exitCode: 127, errorOutput: 'herd: command not found')));

    $this->getJson('/api/projects')
        ->assertServerError()
        ->assertJsonPath('message', 'Unable to reach Herd. Make sure Herd is running and try again.');
});
