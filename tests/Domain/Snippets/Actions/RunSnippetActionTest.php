<?php

declare(strict_types=1);

use Domain\Snippets\Actions\RunSnippetAction;
use Mockery\MockInterface;
use Support\Herd;
use Support\SnippetRunResult;

it('runs the code against the current project when none is given', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('currentProject')->once()->andReturn('tinkerbench');
        $mock->shouldReceive('projectPath')->once()->with('tinkerbench')->andReturn('/path/to/tinkerbench');
        $mock->shouldReceive('phpBinary')->once()->with('tinkerbench')->andReturn('/path/to/php');
        $mock->shouldReceive('runSnippet')->once()
            ->with('return 1 + 1;', '/path/to/php', '/path/to/tinkerbench')
            ->andReturn(new SnippetRunResult('2'));
    });

    $result = resolve(RunSnippetAction::class)->execute('return 1 + 1;');

    expect($result->output)->toBe('2');
});

it('runs the code against a given project', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('currentProject')->never();
        $mock->shouldReceive('projectPath')->once()->with('other-project')->andReturn('/path/to/other-project');
        $mock->shouldReceive('phpBinary')->once()->with('other-project')->andReturn('/path/to/other-project/php');
        $mock->shouldReceive('runSnippet')->once()
            ->with('return 1;', '/path/to/other-project/php', '/path/to/other-project')
            ->andReturn(new SnippetRunResult('1'));
    });

    $result = resolve(RunSnippetAction::class)->execute('return 1;', 'other-project');

    expect($result->output)->toBe('1');
});

it('throws when the resolved project is unknown to herd', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPath')->once()->with('unknown')->andReturn(null);
    });

    resolve(RunSnippetAction::class)->execute('return 1;', 'unknown');
})->throws(RuntimeException::class);
