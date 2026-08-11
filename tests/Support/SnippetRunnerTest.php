<?php

declare(strict_types=1);

use Support\SnippetRunner;

// SnippetRunner::run() requires bootstrap/app.php, which replaces the process-wide Application/facade
// singleton with a second instance distinct from this test suite's own $this->app. Safe today because
// no test here, or later in the same PHPUnit process, resolves a facade after calling run(); adding one
// would silently resolve against the wrong container.
it('echoes the snippet string return value', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    file_put_contents($snippetPath, "<?php\n\nreturn 'hello from the snippet';");

    new SnippetRunner()->run($snippetPath);

    unlink($snippetPath);
})->expectOutputString('hello from the snippet');

it('prints nothing when the snippet does not return a string', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    file_put_contents($snippetPath, "<?php\n\n1 + 1;");

    new SnippetRunner()->run($snippetPath);

    unlink($snippetPath);
})->expectOutputString('');

it('lets an uncaught exception in the snippet propagate', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    file_put_contents($snippetPath, "<?php\n\nthrow new RuntimeException('snippet failed');");

    new SnippetRunner()->run($snippetPath);

    unlink($snippetPath);
})->throws(RuntimeException::class, 'snippet failed');
