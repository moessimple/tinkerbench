<?php

declare(strict_types=1);

use App\Support\SnippetRunner;

// SnippetRunner::run() requires bootstrap/app.php, which replaces the process-wide Application/facade
// singleton with a second instance distinct from this test suite's own $this->app. Safe today because
// no test here, or later in the same PHPUnit process, resolves a facade after calling run(); adding one
// would silently resolve against the wrong container.
it('echoes the snippet string return value', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    $debugPath = tempnam(sys_get_temp_dir(), 'debug');
    file_put_contents($snippetPath, "<?php\n\nreturn 'hello from the snippet';");

    new SnippetRunner()->run(base_path(), $snippetPath, $debugPath);

    unlink($snippetPath);
    unlink($debugPath);
})->expectOutputString('hello from the snippet');

it('prints nothing when the snippet does not return a string', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    $debugPath = tempnam(sys_get_temp_dir(), 'debug');
    file_put_contents($snippetPath, "<?php\n\n1 + 1;");

    new SnippetRunner()->run(base_path(), $snippetPath, $debugPath);

    unlink($snippetPath);
    unlink($debugPath);
})->expectOutputString('');

it('lets an uncaught exception in the snippet propagate', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    $debugPath = tempnam(sys_get_temp_dir(), 'debug');
    file_put_contents($snippetPath, "<?php\n\nthrow new RuntimeException('snippet failed');");

    new SnippetRunner()->run(base_path(), $snippetPath, $debugPath);

    unlink($snippetPath);
    unlink($debugPath);
})->throws(RuntimeException::class, 'snippet failed');

it('writes the collected debug data to the given debug path', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    $debugPath = tempnam(sys_get_temp_dir(), 'debug');
    file_put_contents($snippetPath, "<?php\n\nreturn 'ok';");

    new SnippetRunner()->run(base_path(), $snippetPath, $debugPath);

    $debug = json_decode(file_get_contents($debugPath), true);

    unlink($snippetPath);
    unlink($debugPath);

    expect(data_get($debug, 'time.measures.0.label'))->toBe('snippet');
})->expectOutputString('ok');

it('writes debug data before an uncaught exception propagates', function (): void {
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    $debugPath = tempnam(sys_get_temp_dir(), 'debug');
    file_put_contents($snippetPath, "<?php\n\nthrow new RuntimeException('snippet failed');");

    try {
        new SnippetRunner()->run(base_path(), $snippetPath, $debugPath);
    } catch (RuntimeException) {
        //
    }

    $debug = json_decode(file_get_contents($debugPath), true);

    unlink($snippetPath);
    unlink($debugPath);

    expect(data_get($debug, 'exceptions.count'))->toBe(1)
        ->and(data_get($debug, 'exceptions.exceptions.0.message'))->toBe('snippet failed');
});
