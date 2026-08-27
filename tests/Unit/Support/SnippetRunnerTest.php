<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

/**
 * Runs $code through the real run-snippet.php subprocess against tinkerbench itself, the same
 * entry point Herd::runSnippet() uses, and returns stdout plus the decoded debug snapshot.
 * The subprocess is the only place register_shutdown_function persistence, dd()'s exit(), and
 * uncatchable fatals can be exercised.
 *
 * @return array{output: string, exitCode: int, debug: array<string, mixed>|null}
 */
function runSnippetSubprocess(string $code): array
{
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    $debugPath = tempnam(sys_get_temp_dir(), 'debug');
    file_put_contents($snippetPath, $code);

    $result = Process::env(['VAR_DUMPER_FORMAT' => 'html'])->run([
        PHP_BINARY,
        base_path('app/Support/bin/run-snippet.php'),
        base_path(),
        $snippetPath,
        $debugPath,
    ]);

    $raw = is_file($debugPath) ? (string) file_get_contents($debugPath) : '';
    $decoded = json_decode($raw !== '' ? $raw : 'null', true);

    unlink($snippetPath);

    if (is_file($debugPath)) {
        unlink($debugPath);
    }

    return [
        'output' => $result->output(),
        'exitCode' => $result->exitCode() ?? -1,
        'debug' => is_array($decoded) ? $decoded : null,
    ];
}

it('echoes the snippet string return value', function (): void {
    $result = runSnippetSubprocess("<?php\n\nreturn 'hello from the snippet';");

    expect($result['output'])->toBe('hello from the snippet')
        ->and($result['exitCode'])->toBe(0);
});

it('prints nothing when the snippet does not return a string', function (): void {
    $result = runSnippetSubprocess("<?php\n\n1 + 1;");

    expect($result['output'])->toBe('');
});

it('writes the run snapshot to the debug path', function (): void {
    $result = runSnippetSubprocess("<?php\n\nreturn 'ok';");

    expect($result['debug'])->toHaveKeys(['items', 'duration_str', 'peak_memory_str'])
        ->and($result['debug']['items'])->toBe([])
        ->and($result['debug']['duration_str'])->toMatch('/^\d+\.\d{2}(ms|s)$/')
        ->and($result['debug']['peak_memory_str'])->toMatch('/^\d+\.\d{2}MB$/');
});

it('persists items captured before dd() exits the process', function (): void {
    $result = runSnippetSubprocess("<?php\n\ndump(['a' => 1]);\n\ndd('the end');");

    expect($result['exitCode'])->toBe(1)
        ->and($result['debug']['items'])->toHaveCount(2)
        ->and($result['debug']['items'][0]['kind'])->toBe('dump')
        ->and($result['debug']['items'][1]['kind'])->toBe('dump');
});

it('records an uncaught exception without crashing the process', function (): void {
    $result = runSnippetSubprocess("<?php\n\nthrow new RuntimeException('snippet failed');");

    expect($result['exitCode'])->toBe(0)
        ->and($result['output'])->toBe('')
        ->and($result['debug']['items'])->toHaveCount(1)
        ->and($result['debug']['items'][0]['kind'])->toBe('exception')
        ->and($result['debug']['items'][0]['type'])->toBe(RuntimeException::class)
        ->and($result['debug']['items'][0]['message'])->toBe('snippet failed');
});

it('synthesizes an exception item for a hard fatal via the shutdown handler', function (): void {
    // Memory exhaustion never surfaces as a Throwable, so the try/catch cannot see it; only the
    // shutdown handler's error_get_last() check recovers it into the feed.
    $result = runSnippetSubprocess(
        "<?php\n\nini_set('memory_limit', '48M');\n\n\$acc = [];\n\nwhile (true) {\n    \$acc[] = str_repeat('x', 1024 * 1024);\n}"
    );

    $exceptions = array_values(array_filter(
        $result['debug']['items'] ?? [],
        fn (array $item): bool => $item['kind'] === 'exception',
    ));

    expect($exceptions)->toHaveCount(1)
        ->and($exceptions[0]['message'])->toContain('memory');
});
