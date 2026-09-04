<?php

declare(strict_types=1);

use App\Support\SnippetRun\SourceLocator;

it('returns the snippet line of the first backtrace frame inside the snippet file', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'sl-');
    file_put_contents($snippetPath, "<?php\n\n\$probe();\n");

    $locator = new SourceLocator($snippetPath);
    $captured = null;
    $probe = function () use ($locator, &$captured): void {
        $captured = $locator->snippetLine();
    };

    require $snippetPath;

    unlink($snippetPath);

    expect($captured)->toBe(3);
});

it('returns null when no backtrace frame is inside the snippet file', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'sl-');
    file_put_contents($snippetPath, "<?php\n");

    $line = new SourceLocator($snippetPath)->snippetLine();

    unlink($snippetPath);

    expect($line)->toBeNull();
});

it('reads the line from a throwable raised directly in the snippet file', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'sl-');
    file_put_contents($snippetPath, "<?php\n\nthrow new RuntimeException('x');\n");

    try {
        require $snippetPath;
        $caught = null;
    } catch (RuntimeException $runtimeException) {
        $caught = $runtimeException;
    }

    $line = new SourceLocator($snippetPath)->throwableLine($caught);

    unlink($snippetPath);

    expect($line)->toBe(3);
});

it('reads the snippet call-site line from a throwable raised deeper in the stack', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'sl-');
    file_put_contents($snippetPath, "<?php\n\n\$boom();\n");

    $boom = function (): void {
        throw new RuntimeException('deep');
    };

    try {
        require $snippetPath;
        $caught = null;
    } catch (RuntimeException $runtimeException) {
        $caught = $runtimeException;
    }

    $line = new SourceLocator($snippetPath)->throwableLine($caught);

    unlink($snippetPath);

    expect($line)->toBe(3);
});

it('returns null for a throwable that never touched the snippet file', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'sl-');
    file_put_contents($snippetPath, "<?php\n");

    $line = new SourceLocator($snippetPath)->throwableLine(new RuntimeException('nope'));

    unlink($snippetPath);

    expect($line)->toBeNull();
});
