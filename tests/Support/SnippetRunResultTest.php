<?php

declare(strict_types=1);

use Support\SnippetRunResult;

it('exposes the given output', function (): void {
    $result = new SnippetRunResult('2', null);

    expect($result->output)->toBe('2');
});

it('exposes the given debug data', function (): void {
    $result = new SnippetRunResult('2', ['queries' => ['count' => 1]]);

    expect($result->debug)->toBe(['queries' => ['count' => 1]]);
});

it('exposes null debug data when none was collected', function (): void {
    $result = new SnippetRunResult('2', null);

    expect($result->debug)->toBeNull();
});
