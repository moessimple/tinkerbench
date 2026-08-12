<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Support\Herd;

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

it('throws when the herd bin path is not configured', function (): void {
    config(['services.herd.bin' => '']);

    new Herd()->projects();
})->throws(InvalidArgumentException::class);

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

it('surfaces the process error when the given php binary does not exist', function (): void {
    config(['services.herd.bin' => '/nonexistent-herd-bin']);

    $result = new Herd()->runSnippet("return 'unreachable';", '/nonexistent-herd-bin/php', base_path());

    expect($result->output)->not->toBe('');
});

it('runs a snippet in a subprocess and returns its output', function (): void {
    $result = new Herd()->runSnippet("return 'from the subprocess';", PHP_BINARY, base_path());

    expect($result->output)->toBe('from the subprocess');
});

it('boots the target project so snippets can use its Laravel helpers', function (): void {
    $result = new Herd()->runSnippet("return config('app.name');", PHP_BINARY, base_path());

    expect($result->output)->toBe(config('app.name'));
});

it('lets two snippets that redeclare the same class both succeed', function (): void {
    $herd = new Herd();

    $first = $herd->runSnippet("class DuplicateSnippetClass {}\n\nreturn 'first';", PHP_BINARY, base_path());
    $second = $herd->runSnippet("class DuplicateSnippetClass {}\n\nreturn 'second';", PHP_BINARY, base_path());

    expect($first->output)->toBe('first')
        ->and($second->output)->toBe('second');
});

it('surfaces a thrown exception via the process error output', function (): void {
    $result = new Herd()->runSnippet("throw new RuntimeException('boom');", PHP_BINARY, base_path());

    expect($result->output)->toContain('boom');
});

it('keeps output printed before an uncaught exception instead of discarding it', function (): void {
    $result = new Herd()->runSnippet("echo 'partial output'; throw new RuntimeException('boom');", PHP_BINARY, base_path());

    expect($result->output)->toContain('partial output')
        ->and($result->output)->toContain('boom');
});

it('does not duplicate an opening tag the snippet already provides', function (): void {
    $result = new Herd()->runSnippet("<?php\n\nreturn 'already tagged';", PHP_BINARY, base_path());

    expect($result->output)->toBe('already tagged');
});

it('cleans up the temp snippet file after running', function (): void {
    $before = glob(sys_get_temp_dir().'/tinkerbench-snippet-*.php');

    new Herd()->runSnippet("return 'cleanup check';", PHP_BINARY, base_path());

    $after = glob(sys_get_temp_dir().'/tinkerbench-snippet-*.php');

    expect($after)->toBe($before);
});
