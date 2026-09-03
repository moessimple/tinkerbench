<?php

declare(strict_types=1);

use App\Support\Herd;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

it('merges sites and parked into a project map', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([
            ['site' => 'tinkerbench', 'path' => '/path/to/tinkerbench'],
        ]),
        "*'parked' '--json'" => json_encode([
            ['site' => 'other-project', 'path' => '/path/to/other-project'],
        ]),
    ]);

    expect(new Herd()->projects())->toBe([
        'tinkerbench' => '/path/to/tinkerbench',
        'other-project' => '/path/to/other-project',
    ]);
});

it('ignores entries missing a site or path', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([
            ['site' => 'valid', 'path' => '/path/to/valid'],
            ['path' => '/path/without/a/site'],
            ['site' => 'without-a-path'],
        ]),
        "*'parked' '--json'" => json_encode([]),
    ]);

    expect(new Herd()->projects())->toBe(['valid' => '/path/to/valid']);
});

it('ignores entries that are not themselves json objects', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode(['not-an-object', ['site' => 'valid', 'path' => '/path/to/valid']]),
        "*'parked' '--json'" => json_encode([]),
    ]);

    expect(new Herd()->projects())->toBe(['valid' => '/path/to/valid']);
});

it('treats invalid json output as no projects', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => 'not json',
        "*'parked' '--json'" => 'not json',
    ]);

    expect(new Herd()->projects())->toBe([]);
});

it('shells out to herd only once when called repeatedly on the same instance', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([
            ['site' => 'tinkerbench', 'path' => '/path/to/tinkerbench'],
        ]),
        "*'parked' '--json'" => json_encode([]),
    ]);

    $herd = new Herd();
    $herd->projects();
    $herd->projects();

    Process::assertRanTimes(fn ($process): bool => in_array('sites', $process->command, true), 1);
    Process::assertRanTimes(fn ($process): bool => in_array('parked', $process->command, true), 1);
});

it('shares the project list cache across separate herd instances', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([
            ['site' => 'tinkerbench', 'path' => '/path/to/tinkerbench'],
        ]),
        "*'parked' '--json'" => json_encode([]),
    ]);

    new Herd()->projects();
    new Herd()->projects();

    Process::assertRanTimes(fn ($process): bool => in_array('sites', $process->command, true), 1);
    Process::assertRanTimes(fn ($process): bool => in_array('parked', $process->command, true), 1);
});

it('refreshes the project list explicitly', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([]),
        "*'parked' '--json'" => json_encode([]),
    ]);

    expect(new Herd()->projects())->toBe([]);

    Process::fake([
        "*'sites' '--json'" => json_encode([
            ['site' => 'new-project', 'path' => '/path/to/new-project'],
        ]),
        "*'parked' '--json'" => json_encode([]),
    ]);

    expect(new Herd()->refreshProjects())->toBe([
        'new-project' => '/path/to/new-project',
    ])->and(new Herd()->projects())->toBe([
        'new-project' => '/path/to/new-project',
    ]);
});

it('lists just the project names', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([
            ['site' => 'tinkerbench', 'path' => '/path/to/tinkerbench'],
        ]),
        "*'parked' '--json'" => json_encode([
            ['site' => 'other-project', 'path' => '/path/to/other-project'],
        ]),
    ]);

    expect(new Herd()->projectNames())->toBe(['tinkerbench', 'other-project']);
});

it('throws when the herd bin path is not configured', function (): void {
    config(['services.herd.bin' => '']);

    new Herd()->projects();
})->throws(InvalidArgumentException::class);

it('throws instead of treating stderr as data when a herd command fails', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => Process::result(errorOutput: 'herd: command not found', exitCode: 127),
    ]);

    new Herd()->projects();
})->throws(RuntimeException::class);

it('resolves the real filesystem path for a known project', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([
            ['site' => 'tinkerbench', 'path' => base_path()],
        ]),
        "*'parked' '--json'" => json_encode([]),
    ]);

    expect(new Herd()->projectPath('tinkerbench'))->toBe(realpath(base_path()));
});

it('returns null for an unknown project', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([]),
        "*'parked' '--json'" => json_encode([]),
    ]);

    expect(new Herd()->projectPath('does-not-exist'))->toBeNull();
});

it('finds its own herd site name by matching its own path', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([
            ['site' => 'other-project', 'path' => '/path/to/other-project'],
            ['site' => 'tinkerbench', 'path' => base_path()],
        ]),
        "*'parked' '--json'" => json_encode([]),
    ]);

    expect(new Herd()->currentProject())->toBe('tinkerbench');
});

it('throws when it cannot find its own project among herd projects', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([]),
        "*'parked' '--json'" => json_encode([]),
    ]);

    new Herd()->currentProject();
})->throws(RuntimeException::class);

it('resolves the given project name as is', function (): void {
    expect(new Herd()->resolveProject('given-project'))->toBe('given-project');
});

it('resolves to the current project when none is given', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([
            ['site' => 'tinkerbench', 'path' => base_path()],
        ]),
        "*'parked' '--json'" => json_encode([]),
    ]);

    expect(new Herd()->resolveProject(null))->toBe('tinkerbench');
});

it("resolves a project's php binary via herd", function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'which-php'*" => "/some/project/php\n",
    ]);

    expect(new Herd()->phpBinary('a-project'))->toBe('/some/project/php');
});

it('falls back to the configured herd php binary when herd reports none', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'which-php'*" => '',
    ]);

    expect(new Herd()->phpBinary('a-project'))->toBe('/tmp/herd-bin/php');
});

it('shares the resolved php binary cache across separate herd instances', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'which-php'*" => "/some/project/php\n",
    ]);

    new Herd()->phpBinary('a-project');
    new Herd()->phpBinary('a-project');

    Process::assertRanTimes(fn ($process): bool => in_array('which-php', $process->command, true), 1);
});

it('refreshes the resolved php binary explicitly', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake(["*'which-php'*" => "/some/project/php84\n"]);

    expect(new Herd()->phpBinary('a-project'))->toBe('/some/project/php84');

    Process::fake(["*'which-php'*" => "/some/project/php85\n"]);

    expect(new Herd()->refreshPhpBinary('a-project'))->toBe('/some/project/php85')
        ->and(new Herd()->phpBinary('a-project'))->toBe('/some/project/php85');
});

it('resolves the real php version of a given php binary', function (): void {
    expect(new Herd()->phpVersion(PHP_BINARY))->toBe(PHP_VERSION);
});

it('reports the php version as unknown when the given binary produces no output', function (): void {
    Process::fake(['*' => '']);

    expect(new Herd()->phpVersion('/some/php'))->toBe('unknown');
});

it('resolves the real laravel version of a given project', function (): void {
    expect(new Herd()->laravelVersion(PHP_BINARY, base_path()))->toBe(app()->version());
});

it('reports the laravel version as unknown when the given project produces no output', function (): void {
    Process::fake(['*' => '']);

    expect(new Herd()->laravelVersion('/some/php', '/some/path'))->toBe('unknown');
});

it('shells out for a php version only once, sharing the cache across instances', function (): void {
    Process::fake(['*' => "8.5.0\n"]);

    new Herd()->phpVersion('/some/php');
    new Herd()->phpVersion('/some/php');

    Process::assertRanTimes(fn ($process): bool => in_array('echo PHP_VERSION;', $process->command, true), 1);
});

it('refreshes the cached php version explicitly', function (): void {
    Process::fake(['*' => "8.5.0\n"]);

    expect(new Herd()->phpVersion('/some/php'))->toBe('8.5.0');

    Process::fake(['*' => "8.5.1\n"]);

    expect(new Herd()->refreshPhpVersion('/some/php'))->toBe('8.5.1')
        ->and(new Herd()->phpVersion('/some/php'))->toBe('8.5.1');
});

it('shells out for a laravel version only once, sharing the cache across instances', function (): void {
    Process::fake(['*' => "13.0.0\n"]);

    new Herd()->laravelVersion('/some/php', '/some/path');
    new Herd()->laravelVersion('/some/php', '/some/path');

    Process::assertRanTimes(fn ($process): bool => in_array('/some/php', $process->command, true), 1);
});

it('refreshes the cached laravel version explicitly', function (): void {
    Process::fake(['*' => "13.0.0\n"]);

    expect(new Herd()->laravelVersion('/some/php', '/some/path'))->toBe('13.0.0');

    Process::fake(['*' => "13.1.0\n"]);

    expect(new Herd()->refreshLaravelVersion('/some/php', '/some/path'))->toBe('13.1.0')
        ->and(new Herd()->laravelVersion('/some/php', '/some/path'))->toBe('13.1.0');
});

it('surfaces the process error when the given php binary does not exist', function (): void {
    config(['services.herd.bin' => '/nonexistent-herd-bin']);

    $result = new Herd()->runSnippet("<?php\n\nreturn 'unreachable';", '/nonexistent-herd-bin/php', base_path());

    expect($result->output)->not->toBe('');
});

it('runs a snippet in a subprocess and returns its output', function (): void {
    $result = new Herd()->runSnippet("<?php\n\nreturn 'from the subprocess';", PHP_BINARY, base_path());

    expect($result->output)->toBe('from the subprocess');
});

it('boots the target project so snippets can use its Laravel helpers', function (): void {
    $result = new Herd()->runSnippet("<?php\n\nreturn config('app.name');", PHP_BINARY, base_path());

    expect($result->output)->toBe(config('app.name'));
});

it('lets two snippets that redeclare the same class both succeed', function (): void {
    $herd = new Herd();

    $first = $herd->runSnippet("<?php\n\nclass DuplicateSnippetClass {}\n\nreturn 'first';", PHP_BINARY, base_path());
    $second = $herd->runSnippet("<?php\n\nclass DuplicateSnippetClass {}\n\nreturn 'second';", PHP_BINARY, base_path());

    expect($first->output)->toBe('first')
        ->and($second->output)->toBe('second');
});

it('does not crash the subprocess when the snippet throws', function (): void {
    $result = new Herd()->runSnippet("<?php\n\nthrow new RuntimeException('boom');", PHP_BINARY, base_path());

    expect($result->output)->toBe('')
        ->and($result->debug)->not->toBeNull();
});

it('keeps output printed before an uncaught exception instead of discarding it', function (): void {
    $result = new Herd()->runSnippet("<?php\n\necho 'partial output'; throw new RuntimeException('boom');", PHP_BINARY, base_path());

    expect($result->output)->toContain('partial output');
});

it('echoes the snippet back verbatim when it has no opening tag', function (): void {
    $result = new Herd()->runSnippet("return 'missing tag';", PHP_BINARY, base_path());

    expect($result->output)->toBe("return 'missing tag';");
});

it('captures dump() as an HTML dump item in the debug data', function (): void {
    $result = new Herd()->runSnippet("<?php\n\ndump('hello');", PHP_BINARY, base_path());

    expect($result->output)->toBe('')
        ->and(data_get($result->debug, 'items.0.kind'))->toBe('dump')
        ->and(data_get($result->debug, 'items.0.html'))->toContain('Sfdump(');
});

it('cleans up the temp snippet file after running', function (): void {
    $scratch = sys_get_temp_dir().'/tinkerbench-herd-test-'.Str::random(16);
    File::makeDirectory($scratch);

    try {
        new Herd($scratch)->runSnippet("<?php\n\nreturn 'cleanup check';", PHP_BINARY, base_path());

        expect(glob($scratch.'/tinkerbench-snippet-*.php'))->toBe([]);
    } finally {
        File::deleteDirectory($scratch);
    }
});

it('cleans up the temp debug file after running', function (): void {
    $scratch = sys_get_temp_dir().'/tinkerbench-herd-test-'.Str::random(16);
    File::makeDirectory($scratch);

    try {
        new Herd($scratch)->runSnippet("<?php\n\nreturn 'cleanup check';", PHP_BINARY, base_path());

        expect(glob($scratch.'/tinkerbench-debug-*.json'))->toBe([]);
    } finally {
        File::deleteDirectory($scratch);
    }
});

it('returns the debug data collected by the subprocess', function (): void {
    $result = new Herd()->runSnippet("<?php\n\nreturn 'ok';", PHP_BINARY, base_path());

    expect($result->debug)->toHaveKeys(['items', 'duration_str', 'peak_memory_str']);
});

it('kills a snippet that runs past its timeout and returns a graceful result instead of hanging', function (): void {
    $result = new Herd()->runSnippet("<?php\n\nsleep(5);", PHP_BINARY, base_path(), timeoutSeconds: 1);

    expect($result->output)->toContain('Snippet timed out after 1 seconds.')
        ->and($result->debug)->toBeNull();
});

it('returns an exception item in the debug data for an uncaught throw', function (): void {
    $result = new Herd()->runSnippet("<?php\n\nthrow new RuntimeException('boom');", PHP_BINARY, base_path());

    expect(data_get($result->debug, 'items.0.kind'))->toBe('exception')
        ->and(data_get($result->debug, 'items.0.message'))->toBe('boom');
});
