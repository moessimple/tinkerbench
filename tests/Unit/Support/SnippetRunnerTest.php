<?php

declare(strict_types=1);

use App\Support\SnippetRunner;
use App\Support\SnippetRunRecorder;
use App\Support\SourceLocator;
use Illuminate\Support\Facades\Process;
use Symfony\Component\VarDumper\VarDumper;

/**
 * @return array{items: list<mixed>, duration_str: string, peak_memory_str: string}
 */
function fixtureSnapshot(): array
{
    return ['items' => [], 'duration_str' => '1.00ms', 'peak_memory_str' => '1.00 MB'];
}

// An in-process run() installs a process-wide VarDumper handler via DumpWatcher and never
// restores it; without this reset a dump() in a later test would be swallowed by the finished
// run's recorder instead of reaching stdout.
afterEach(function (): void {
    VarDumper::setHandler(null);
});

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
        ->and($result['debug']['peak_memory_str'])->toMatch('/^[\d,]+\.\d{2} MB$/');
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

it('trims the exception trace to the snippet, dropping the runner frames', function (): void {
    $result = runSnippetSubprocess("<?php\n\nthrow new RuntimeException('boom');");

    $frames = $result['debug']['items'][0]['frames'];

    expect($frames)->toHaveCount(1)
        ->and($frames[0]['snippet'])->toBeTrue()
        ->and($frames[0]['line'])->toBe(3);

    foreach ($frames as $frame) {
        expect($frame['function'] ?? '')->not->toContain('SnippetRunner')
            ->and($frame['function'] ?? '')->not->toContain('SnippetRunRecorder');
    }
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

// In-process runs exercise run()'s own wiring against tinkerbench itself. The shutdown handler
// it registers no-ops at PHPUnit exit because run() has already persisted inline.

function runInProcess(string $code): array
{
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    $debugPath = tempnam(sys_get_temp_dir(), 'debug');
    file_put_contents($snippetPath, $code);

    new SnippetRunner()->run(base_path(), $snippetPath, $debugPath);

    $snapshot = json_decode((string) file_get_contents($debugPath), true);

    unlink($snippetPath);
    unlink($debugPath);

    return is_array($snapshot) ? $snapshot : [];
}

it('echoes a string return from an in-process run and writes the snapshot', function (): void {
    $snapshot = runInProcess("<?php\n\nreturn 'inprocess hello';");

    expect($snapshot)->toHaveKeys(['items', 'duration_str', 'peak_memory_str']);
})->expectOutputString('inprocess hello');

it('prints nothing for a non-string return from an in-process run', function (): void {
    runInProcess("<?php\n\n1 + 1;");
})->expectOutputString('');

it('records a thrown exception from an in-process run without re-throwing', function (): void {
    $snapshot = runInProcess("<?php\n\nthrow new RuntimeException('inprocess boom');");

    expect($snapshot['items'][0]['kind'])->toBe('exception')
        ->and($snapshot['items'][0]['message'])->toBe('inprocess boom');
});

it('persist writes the snapshot and records no exception for a null last error', function (): void {
    $debugPath = tempnam(sys_get_temp_dir(), 'persist');

    $recorder = Mockery::mock(SnippetRunRecorder::class);
    $recorder->shouldReceive('snapshot')->andReturn(fixtureSnapshot());
    $recorder->shouldNotReceive('appendException');

    new SnippetRunner()->persist($recorder, new SourceLocator('/x'), $debugPath, null);

    $written = json_decode((string) file_get_contents($debugPath), true);
    unlink($debugPath);

    expect($written)->toBe(fixtureSnapshot());
});

it('persist writes valid JSON even when the snapshot carries non-UTF-8 bytes', function (): void {
    $debugPath = tempnam(sys_get_temp_dir(), 'persist');

    $recorder = Mockery::mock(SnippetRunRecorder::class);
    $recorder->shouldReceive('snapshot')->andReturn([
        'items' => [['kind' => 'dump', 'html' => "bad \xff\xfe bytes", 'line' => null]],
        'duration_str' => '1.00ms',
        'peak_memory_str' => '1.00 MB',
    ]);

    new SnippetRunner()->persist($recorder, new SourceLocator('/x'), $debugPath, null);

    $decoded = json_decode((string) file_get_contents($debugPath), true);
    unlink($debugPath);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded['items'])->toHaveCount(1)
        ->and($decoded['items'][0]['kind'])->toBe('dump');
});

it('persist synthesizes an exception item from a fatal-class last error', function (): void {
    $debugPath = tempnam(sys_get_temp_dir(), 'persist');

    $recorder = Mockery::mock(SnippetRunRecorder::class);
    $recorder->shouldReceive('appendException')->once()->withArgs(
        fn (Throwable $throwable, ?int $line, bool $includeFrames): bool => $throwable instanceof ErrorException
            && $throwable->getMessage() === 'oom'
            && $includeFrames === false,
    );
    $recorder->shouldReceive('snapshot')->andReturn(fixtureSnapshot());

    new SnippetRunner()->persist(
        $recorder,
        new SourceLocator('/x'),
        $debugPath,
        ['type' => E_ERROR, 'message' => 'oom', 'file' => '/x', 'line' => 1],
    );

    unlink($debugPath);
});

it('persist ignores a non-fatal last error', function (): void {
    $debugPath = tempnam(sys_get_temp_dir(), 'persist');

    $recorder = Mockery::mock(SnippetRunRecorder::class);
    $recorder->shouldReceive('snapshot')->andReturn(fixtureSnapshot());
    $recorder->shouldNotReceive('appendException');

    new SnippetRunner()->persist(
        $recorder,
        new SourceLocator('/x'),
        $debugPath,
        ['type' => E_WARNING, 'message' => 'just a warning', 'file' => '/x', 'line' => 1],
    );

    unlink($debugPath);
});

it('persist writes the snapshot only once', function (): void {
    $debugPath = tempnam(sys_get_temp_dir(), 'persist');

    $recorder = Mockery::mock(SnippetRunRecorder::class);
    $recorder->shouldReceive('snapshot')->once()->andReturn(fixtureSnapshot());

    $runner = new SnippetRunner();
    $runner->persist($recorder, new SourceLocator('/x'), $debugPath, null);
    $runner->persist($recorder, new SourceLocator('/x'), $debugPath, null);

    unlink($debugPath);
});
