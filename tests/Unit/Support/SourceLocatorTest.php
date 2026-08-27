<?php

declare(strict_types=1);

use App\Support\SourceLocator;

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
