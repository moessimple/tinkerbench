<?php

declare(strict_types=1);

use App\Support\SnippetRun\SnippetRunner;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

it('surfaces the process error when the given php binary does not exist', function (): void {
    $result = new SnippetRunner()->run("<?php\n\nreturn 'unreachable';", '/nonexistent/php', base_path());

    expect($result->output)->not->toBe('');
});

it('invokes the runner package bin script', function (): void {
    Process::fake();

    new SnippetRunner()->run("<?php\n\nreturn 'unreachable';", PHP_BINARY, base_path());

    Process::assertRan(fn (PendingProcess $process): bool => in_array(base_path('packages/runner/bin/run-snippet.php'), Arr::wrap($process->command), true));
});

it('runs a snippet in a subprocess and returns its return value as a result item', function (): void {
    $result = new SnippetRunner()->run("<?php\n\nreturn 'from the subprocess';", PHP_BINARY, base_path());

    expect($result->output)->toBe('')
        ->and(data_get($result->debug, 'items'))->toHaveCount(1)
        ->and(data_get($result->debug, 'items.0.kind'))->toBe('result')
        ->and(data_get($result->debug, 'items.0.html'))->toContain('from the subprocess');
});

it('boots the target project so snippets can use its Laravel helpers', function (): void {
    $result = new SnippetRunner()->run("<?php\n\nreturn config('app.name');", PHP_BINARY, base_path());

    expect(data_get($result->debug, 'items.0.html'))->toContain(config('app.name'));
});

it('lets a target Laravel project load its own environment values', function (): void {
    $target = base_path('packages/runner/tests/fixtures/laravel-12');
    $environmentPath = $target.'/.env';

    expect(File::exists($environmentPath))->toBeFalse();

    File::put($environmentPath, "APP_NAME=target-project\n");

    try {
        $result = new SnippetRunner()->run("<?php\n\nreturn [env('APP_NAME'), env('APP_URL'), getcwd()];", PHP_BINARY, $target);
    } finally {
        File::delete($environmentPath);
    }

    expect($result->output)->toBe('')
        ->and(data_get($result->debug, 'items.0.kind'))->toBe('result')
        ->and(data_get($result->debug, 'items.0.html'))->toContain('target-project')
        ->toContain($target)
        ->not->toContain('tinkerbench.test');
});

it('clears its own configuration variables for the target process without touching host variables', function (): void {
    Process::fake();

    new SnippetRunner()->run("<?php\n\nreturn 'unreachable';", PHP_BINARY, base_path());

    Process::assertRan(fn (PendingProcess $process): bool => ($process->environment['APP_NAME'] ?? null) === false
        && ($process->environment['DB_CONNECTION'] ?? null) === false
        && ($process->environment['CACHE_STORE'] ?? null) === false
        && ! array_key_exists('PATH', $process->environment)
        && ($process->environment['PWD'] ?? null) === base_path()
        && ($process->environment['VAR_DUMPER_FORMAT'] ?? null) === 'html'
        && $process->path === base_path());
});

it('lets two snippets that redeclare the same class both succeed', function (): void {
    $runner = new SnippetRunner();

    $first = $runner->run("<?php\n\nclass DuplicateSnippetClass {}\n\nreturn 'first';", PHP_BINARY, base_path());
    $second = $runner->run("<?php\n\nclass DuplicateSnippetClass {}\n\nreturn 'second';", PHP_BINARY, base_path());

    expect(data_get($first->debug, 'items.0.html'))->toContain('first')
        ->and(data_get($second->debug, 'items.0.html'))->toContain('second')
        ->and(data_get($first->debug, 'items.*.kind'))->not->toContain('exception')
        ->and(data_get($second->debug, 'items.*.kind'))->not->toContain('exception');
});

it('does not crash the subprocess when the snippet throws', function (): void {
    $result = new SnippetRunner()->run("<?php\n\nthrow new RuntimeException('boom');", PHP_BINARY, base_path());

    expect($result->output)->toBe('')
        ->and($result->debug)->not->toBeNull();
});

it('keeps output printed before an uncaught exception instead of discarding it', function (): void {
    $result = new SnippetRunner()->run("<?php\n\necho 'partial output'; throw new RuntimeException('boom');", PHP_BINARY, base_path());

    expect($result->output)->toContain('partial output');
});

it('echoes the snippet back verbatim when it has no opening tag', function (): void {
    $result = new SnippetRunner()->run("return 'missing tag';", PHP_BINARY, base_path());

    expect($result->output)->toBe("return 'missing tag';");
});

it('captures dump() as an HTML dump item in the debug data', function (): void {
    $result = new SnippetRunner()->run("<?php\n\ndump('hello');", PHP_BINARY, base_path());

    expect($result->output)->toBe('')
        ->and(data_get($result->debug, 'items.0.kind'))->toBe('dump')
        ->and(data_get($result->debug, 'items.0.html'))->toContain('Sfdump(');
});

it('cleans up the temp snippet file after running', function (): void {
    $scratch = sys_get_temp_dir().'/tinkerbench-runner-test-'.Str::random(16);
    File::makeDirectory($scratch);

    try {
        new SnippetRunner($scratch)->run("<?php\n\nreturn 'cleanup check';", PHP_BINARY, base_path());

        expect(glob($scratch.'/tinkerbench-snippet-*.php'))->toBe([]);
    } finally {
        File::deleteDirectory($scratch);
    }
});

it('cleans up the temp debug file after running', function (): void {
    $scratch = sys_get_temp_dir().'/tinkerbench-runner-test-'.Str::random(16);
    File::makeDirectory($scratch);

    try {
        new SnippetRunner($scratch)->run("<?php\n\nreturn 'cleanup check';", PHP_BINARY, base_path());

        expect(glob($scratch.'/tinkerbench-debug-*.json'))->toBe([]);
    } finally {
        File::deleteDirectory($scratch);
    }
});

it('returns the debug data collected by the subprocess', function (): void {
    $result = new SnippetRunner()->run("<?php\n\nreturn 'ok';", PHP_BINARY, base_path());

    expect($result->debug)->toHaveKeys(['items', 'duration_str', 'peak_memory_str']);
});

it('kills a snippet that runs past its timeout and returns a graceful result instead of hanging', function (): void {
    $result = new SnippetRunner()->run("<?php\n\nsleep(5);", PHP_BINARY, base_path(), timeoutSeconds: 1);

    expect($result->output)->toContain('Snippet timed out after 1 seconds.')
        ->and($result->debug)->toBeNull();
});

it('returns an exception item in the debug data for an uncaught throw', function (): void {
    $result = new SnippetRunner()->run("<?php\n\nthrow new RuntimeException('boom');", PHP_BINARY, base_path());

    expect(data_get($result->debug, 'items.0.kind'))->toBe('exception')
        ->and(data_get($result->debug, 'items.0.message'))->toBe('boom');
});

it('returns no debug data when the debug file was left truncated by a killed subprocess', function (): void {
    Process::fake(function (PendingProcess $process) {
        // The debug path is the fifth and last argument run-snippet.php is invoked with.
        $debugPath = Arr::wrap($process->command)[4] ?? null;

        if (is_string($debugPath)) {
            file_put_contents($debugPath, '{"items": [');
        }

        return Process::result(output: '');
    });

    $result = new SnippetRunner()->run("<?php\n\nreturn 1;", PHP_BINARY, base_path());

    expect($result->debug)->toBeNull();
});
