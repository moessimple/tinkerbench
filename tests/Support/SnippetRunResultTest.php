<?php

declare(strict_types=1);

use Support\SnippetRunResult;

it('exposes the given output', function (): void {
    $result = new SnippetRunResult('2');

    expect($result->output)->toBe('2');
});
