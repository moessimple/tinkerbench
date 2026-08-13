<?php

declare(strict_types=1);

use Domain\Snippets\Actions\RunSnippetAction;
use Mockery\MockInterface;
use Support\Herd;
use Support\SnippetRunResult;

it('runs the code against the given project', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPath')->once()->with('other-project')->andReturn('/path/to/other-project');
        $mock->shouldReceive('phpBinary')->once()->with('other-project')->andReturn('/path/to/other-project/php');
        $mock->shouldReceive('runSnippet')->once()
            ->with('return 1;', '/path/to/other-project/php', '/path/to/other-project')
            ->andReturn(new SnippetRunResult('1'));
    });

    $result = resolve(RunSnippetAction::class)->execute('other-project', 'return 1;');

    expect($result->output)->toBe('1');
});

it('throws when the given project is unknown to herd', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPath')->once()->with('unknown')->andReturn(null);
    });

    resolve(RunSnippetAction::class)->execute('unknown', 'return 1;');
})->throws(RuntimeException::class);
