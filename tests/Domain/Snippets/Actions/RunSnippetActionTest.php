<?php

declare(strict_types=1);

use Domain\Snippets\Actions\RunSnippetAction;
use Support\HerdContract;
use Support\SnippetRunResult;

test('uses HerdContract to run the code', function (): void {
    $this->mock(HerdContract::class)
        ->shouldReceive('runSnippet')->once()->with('return 1 + 1;')
        ->andReturn(new SnippetRunResult('2'));

    $result = resolve(RunSnippetAction::class)->execute('return 1 + 1;');

    expect($result->output)->toBe('2');
});
