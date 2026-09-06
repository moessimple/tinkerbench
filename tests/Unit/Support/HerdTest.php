<?php

declare(strict_types=1);

use App\Support\Herd;
use App\Support\ProjectSnapshot;
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

it('re-pulls the project list from herd when asked for a fresh copy', function (): void {
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

    expect(new Herd()->projects(fresh: true))->toBe([
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

it('returns the resolved path when requiring a known project', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([
            ['site' => 'tinkerbench', 'path' => base_path()],
        ]),
        "*'parked' '--json'" => json_encode([]),
    ]);

    expect(new Herd()->projectPathOrFail('tinkerbench'))->toBe(realpath(base_path()));
});

it('throws when requiring the path of a project unknown to herd', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([]),
        "*'parked' '--json'" => json_encode([]),
    ]);

    new Herd()->projectPathOrFail('does-not-exist');
})->throws(RuntimeException::class, 'Unknown Herd project: does-not-exist');

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

it('re-resolves the php binary from herd when asked for a fresh copy', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake(["*'which-php'*" => "/some/project/php84\n"]);

    expect(new Herd()->phpBinary('a-project'))->toBe('/some/project/php84');

    Process::fake(["*'which-php'*" => "/some/project/php85\n"]);

    expect(new Herd()->phpBinary('a-project', fresh: true))->toBe('/some/project/php85')
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

    expect(new Herd()->laravelVersion('/some/php', base_path()))->toBe('unknown');
});

it('reports the laravel version as unknown for a project with no vendor autoloader, without shelling out', function (): void {
    Process::fake();
    $projectPath = sys_get_temp_dir().'/tb-no-composer-'.Str::random(8);
    File::ensureDirectoryExists($projectPath);

    expect(new Herd()->laravelVersion('/some/php', $projectPath))->toBe('unknown');

    Process::assertNotRan(fn ($process): bool => in_array('/some/php', $process->command, true));

    File::deleteDirectory($projectPath);
});

it('shells out for a php version only once, sharing the cache across instances', function (): void {
    Process::fake(['*' => "8.5.0\n"]);

    new Herd()->phpVersion('/some/php');
    new Herd()->phpVersion('/some/php');

    Process::assertRanTimes(fn ($process): bool => in_array('echo PHP_VERSION;', $process->command, true), 1);
});

it('re-resolves the php version when asked for a fresh copy', function (): void {
    Process::fake(['*' => "8.5.0\n"]);

    expect(new Herd()->phpVersion('/some/php'))->toBe('8.5.0');

    Process::fake(['*' => "8.5.1\n"]);

    expect(new Herd()->phpVersion('/some/php', fresh: true))->toBe('8.5.1')
        ->and(new Herd()->phpVersion('/some/php'))->toBe('8.5.1');
});

it('shells out for a laravel version only once, sharing the cache across instances', function (): void {
    Process::fake(['*' => "13.0.0\n"]);

    new Herd()->laravelVersion('/some/php', base_path());
    new Herd()->laravelVersion('/some/php', base_path());

    Process::assertRanTimes(fn ($process): bool => in_array('/some/php', $process->command, true), 1);
});

it('re-resolves the laravel version when asked for a fresh copy', function (): void {
    Process::fake(['*' => "13.0.0\n"]);

    expect(new Herd()->laravelVersion('/some/php', base_path()))->toBe('13.0.0');

    Process::fake(['*' => "13.1.0\n"]);

    expect(new Herd()->laravelVersion('/some/php', base_path(), fresh: true))->toBe('13.1.0')
        ->and(new Herd()->laravelVersion('/some/php', base_path()))->toBe('13.1.0');
});

it('bundles the project path and toolchain versions into a snapshot for a known site', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([['site' => 'tinkerbench', 'path' => base_path()]]),
        "*'parked' '--json'" => json_encode([]),
        "*'which-php'*" => "/fake/php\n",
        "*'echo PHP_VERSION;'" => "8.5.0\n",
        '*' => "13.0.0\n",
    ]);

    expect(new Herd()->snapshotProject('tinkerbench'))->toEqual(new ProjectSnapshot(
        name: 'tinkerbench',
        path: realpath(base_path()),
        phpBinary: '/fake/php',
        phpVersion: '8.5.0',
        laravelVersion: '13.0.0',
    ));
});

it('returns no snapshot for a name that is not a known herd site', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([]),
        "*'parked' '--json'" => json_encode([]),
    ]);

    expect(new Herd()->snapshotProject('does-not-exist'))->toBeNull();
});

it('snapshots the current project when no name is given', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    Process::fake([
        "*'sites' '--json'" => json_encode([['site' => 'tinkerbench', 'path' => base_path()]]),
        "*'parked' '--json'" => json_encode([]),
        "*'which-php'*" => "/fake/php\n",
        "*'echo PHP_VERSION;'" => "8.5.0\n",
        '*' => "13.0.0\n",
    ]);

    expect(new Herd()->snapshotProject(null)?->name)->toBe('tinkerbench');
});

it('serves the cache by default but re-resolves every value for a fresh snapshot', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);
    $sites = [
        "*'sites' '--json'" => json_encode([['site' => 'tinkerbench', 'path' => base_path()]]),
        "*'parked' '--json'" => json_encode([]),
    ];
    Process::fake([...$sites, "*'which-php'*" => "/fake/php\n", "*'echo PHP_VERSION;'" => "8.5.0\n", '*' => "13.0.0\n"]);

    new Herd()->snapshotProject('tinkerbench');

    Process::fake([...$sites, "*'which-php'*" => "/fake/php85\n", "*'echo PHP_VERSION;'" => "8.5.1\n", '*' => "13.1.0\n"]);

    $cached = new Herd()->snapshotProject('tinkerbench');
    $fresh = new Herd()->snapshotProject('tinkerbench', fresh: true);

    expect($cached?->phpVersion)->toBe('8.5.0')
        ->and($cached?->laravelVersion)->toBe('13.0.0')
        ->and($fresh?->phpBinary)->toBe('/fake/php85')
        ->and($fresh?->phpVersion)->toBe('8.5.1')
        ->and($fresh?->laravelVersion)->toBe('13.1.0');
});
