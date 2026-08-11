<?php

declare(strict_types=1);

use Support\SnippetRunner;

test('echoes the snippet string return value', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    file_put_contents($snippetPath, "<?php\n\nreturn 'hello from the snippet';");

    new SnippetRunner()->run($snippetPath);

    unlink($snippetPath);
})->expectOutputString('hello from the snippet');

test('prints nothing when the snippet does not return a string', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    file_put_contents($snippetPath, "<?php\n\n1 + 1;");

    new SnippetRunner()->run($snippetPath);

    unlink($snippetPath);
})->expectOutputString('');

test('lets an uncaught exception in the snippet propagate', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    file_put_contents($snippetPath, "<?php\n\nthrow new RuntimeException('snippet failed');");

    new SnippetRunner()->run($snippetPath);

    unlink($snippetPath);
})->throws(RuntimeException::class, 'snippet failed');
