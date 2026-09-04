<?php

declare(strict_types=1);

use App\Actions\RunSnippetAction;
use App\Support\Herd;
use App\Support\SnippetRun\SnippetRunResult;
use Mockery\MockInterface;

it('runs the code against the given project', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPathOrFail')->once()->with('other-project')->andReturn('/path/to/other-project');
        $mock->shouldReceive('phpBinary')->once()->with('other-project')->andReturn('/path/to/other-project/php');
        $mock->shouldReceive('runSnippet')->once()
            ->with('return 1;', '/path/to/other-project/php', '/path/to/other-project')
            ->andReturn(new SnippetRunResult('1', null));
    });

    $result = resolve(RunSnippetAction::class)->execute('other-project', 'return 1;');

    expect($result->output)->toBe('1');
});

it('propagates the failure when the given project is unknown to herd', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPathOrFail')->once()->with('unknown')->andThrow(new RuntimeException('Unknown Herd project: unknown'));
    });

    resolve(RunSnippetAction::class)->execute('unknown', 'return 1;');
})->throws(RuntimeException::class, 'Unknown Herd project: unknown');
