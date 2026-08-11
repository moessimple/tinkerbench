<?php

declare(strict_types=1);

use Domain\Snippets\Actions\RunSnippetAction;
use Support\Herd;
use Support\SnippetRunResult;

it('runs the code and returns the output', function (): void {
    $this->mock(Herd::class)
        ->shouldReceive('runSnippet')->once()->with('return 1 + 1;')
        ->andReturn(new SnippetRunResult('2'));

    $result = resolve(RunSnippetAction::class)->execute('return 1 + 1;');

    expect($result->output)->toBe('2');
});
