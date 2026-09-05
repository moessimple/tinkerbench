<?php

declare(strict_types=1);

use App\Models\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Process;
use Symfony\Component\VarDumper\VarDumper;
use Tinkerbench\Runner\SnippetRunner;
use Tinkerbench\Runner\SnippetRunRecorder;
use Tinkerbench\Runner\SourceLocator;

/**
 * @return array{items: list<mixed>, duration_str: string, peak_memory_str: string}
 */
function fixtureSnapshot(): array
{
    return ['items' => [], 'duration_str' => '1.00ms', 'peak_memory_str' => '1.00 MB'];
}

/**
 * The monorepo root, used as a real, always-present "target project" to run snippets against
 * (same role Herd::runSnippet() gives the actual target's project path). base_path() cannot be
 * used here: under Orchestra\Testbench\TestCase it resolves to Testbench's own disposable
 * skeleton app, not this repository.
 */
function runnerTargetPath(): string
{
    return dirname(__DIR__, 3);
}

/*
|--------------------------------------------------------------------------
| Against a real target project (tinkerbench itself)
|--------------------------------------------------------------------------
|
| tinkerbench itself is the stand-in "target project" for the tests below (real subprocess and
| in-process runs), the only real, always-present Laravel app in this repository. But tinkerbench's
| own floor is Laravel 13 / PHP 8.5+, so it can't boot under a lower PHP. Every other test in this
| package's suite already proves the ^8.2 floor (they don't depend on booting any target); each test
| below specifically needs a target that itself supports whatever PHP is currently running the
| suite, so each one is individually skipped rather than failing on an unrelated cause when that's
| not the case.
|
*/

const TARGET_REQUIRES_PHP85 = 'tinkerbench itself (the stand-in target project) requires PHP 8.5+; this test needs a target that supports whatever PHP is currently running the suite.';

// An in-process run() leaves the process-wide state its watchers install and never restore: the
// VarDumper handler (DumpWatcher), and preventLazyLoading plus the violation callback
// (LazyLoadWatcher). Without this reset a dump() in a later test is swallowed by the finished
// run's recorder, and a later test that lazy-loads a relation hits the stale violation handler.
afterEach(function (): void {
    VarDumper::setHandler(null);
    Model::preventLazyLoading(false);
    Model::handleLazyLoadingViolationUsing(null);
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
    return runSnippetSubprocessAgainst(runnerTargetPath(), $code);
}

/**
 * Path to a committed fixture project under tests/fixtures/, each a real Composer project whose
 * dependencies CI installs fresh (vendor/ gitignored).
 */
function fixtureTargetPath(string $name): string
{
    return __DIR__.'/fixtures/'.$name;
}

/**
 * As runSnippetSubprocess(), but against an arbitrary target project path instead of tinkerbench
 * itself, so a fixture below tinkerbench's own PHP/Laravel floor can be exercised end to end.
 *
 * @return array{output: string, exitCode: int, debug: array<string, mixed>|null}
 */
function runSnippetSubprocessAgainst(string $targetPath, string $code): array
{
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    $debugPath = tempnam(sys_get_temp_dir(), 'debug');
    file_put_contents($snippetPath, $code);

    $result = Process::env(['VAR_DUMPER_FORMAT' => 'html'])->run([
        PHP_BINARY,
        dirname(__DIR__).'/bin/run-snippet.php',
        $targetPath,
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

it('records the snippet return value as a result item instead of printing it', function (): void {
    $result = runSnippetSubprocess("<?php\n\nreturn ['a' => 1, 'b' => 2];");

    expect($result['output'])->toBe('')
        ->and($result['exitCode'])->toBe(0)
        ->and($result['debug']['items'])->toHaveCount(1)
        ->and($result['debug']['items'][0]['kind'])->toBe('result')
        ->and($result['debug']['items'][0]['html'])->toContain('array:2')
        ->and($result['debug']['items'][0])->not->toHaveKey('line');
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85);

it('records a result item after the items captured during the run', function (): void {
    $result = runSnippetSubprocess("<?php\n\ndump('side effect');\n\nreturn 'the value';");

    expect(array_column($result['debug']['items'], 'kind'))->toBe(['dump', 'result'])
        ->and($result['debug']['items'][1]['html'])->toContain('the value');
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85);

it('records no result item when the snippet has no return statement', function (): void {
    $result = runSnippetSubprocess("<?php\n\n\$x = 1 + 1;");

    expect($result['output'])->toBe('')
        ->and($result['debug']['items'])->toBe([]);
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85);

it('records no result item for a literal return of 1, which it cannot tell from no return', function (): void {
    $result = runSnippetSubprocess("<?php\n\nreturn 1;");

    expect($result['debug']['items'])->toBe([]);
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85);

it('writes the run snapshot to the debug path', function (): void {
    $result = runSnippetSubprocess("<?php\n\n\$x = 'ok';");

    expect($result['debug'])->toHaveKeys(['items', 'duration_str', 'peak_memory_str'])
        ->and($result['debug']['items'])->toBe([])
        ->and($result['debug']['duration_str'])->toMatch('/^\d+\.\d{2}(ms|s)$/')
        ->and($result['debug']['peak_memory_str'])->toMatch('/^[\d,]+\.\d{2} MB$/');
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85);

it('persists items captured before dd() exits the process', function (): void {
    $result = runSnippetSubprocess("<?php\n\ndump(['a' => 1]);\n\ndd('the end');");

    expect($result['exitCode'])->toBe(1)
        ->and($result['debug']['items'])->toHaveCount(2)
        ->and($result['debug']['items'][0]['kind'])->toBe('dump')
        ->and($result['debug']['items'][1]['kind'])->toBe('dump');
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85);

it('records an uncaught exception without crashing the process', function (): void {
    $result = runSnippetSubprocess("<?php\n\nthrow new RuntimeException('snippet failed');");

    expect($result['exitCode'])->toBe(0)
        ->and($result['output'])->toBe('')
        ->and($result['debug']['items'])->toHaveCount(1)
        ->and($result['debug']['items'][0]['kind'])->toBe('exception')
        ->and($result['debug']['items'][0]['type'])->toBe(RuntimeException::class)
        ->and($result['debug']['items'][0]['message'])->toBe('snippet failed');
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85);

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
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85);

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
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85);

/**
 * A snippet that defines an in-memory NPlusOneAuthor hasMany NPlusOneBook pair, seeds three
 * author/book rows, then runs $accessLoop (the relation-access code under test).
 */
function nPlusOneSnippet(string $accessLoop): string
{
    $setup = <<<'PHP'
    <?php

    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    config(['database.connections.nplus' => ['driver' => 'sqlite', 'database' => ':memory:']]);

    Schema::connection('nplus')->create('n_plus_one_authors', function (Blueprint $table): void {
        $table->increments('id');
    });

    Schema::connection('nplus')->create('n_plus_one_books', function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('author_id');
    });

    class NPlusOneAuthor extends Model
    {
        protected $connection = 'nplus';
        protected $table = 'n_plus_one_authors';
        protected $guarded = [];
        public $timestamps = false;

        public function books()
        {
            return $this->hasMany(NPlusOneBook::class, 'author_id');
        }
    }

    class NPlusOneBook extends Model
    {
        protected $connection = 'nplus';
        protected $table = 'n_plus_one_books';
        protected $guarded = [];
        public $timestamps = false;
    }

    foreach (range(1, 3) as $id) {
        NPlusOneAuthor::create(['id' => $id]);
        NPlusOneBook::create(['id' => $id, 'author_id' => $id]);
    }
    PHP;

    return $setup."\n\n".$accessLoop."\n";
}

it('aggregates a lazy-loaded relation from a real run into one n_plus_one item', function (): void {
    // automaticallyEagerLoadRelationships(false): stand in for a project that does not batch lazy
    // loads, so each $author->books is a genuine lazy load. tinkerbench itself boots with
    // nunomaduro/essentials, which does batch, so the snippet opts out to exercise the standard path.
    $result = runSnippetSubprocess(nPlusOneSnippet(<<<'PHP'
    Model::automaticallyEagerLoadRelationships(false);

    foreach (NPlusOneAuthor::all() as $author) {
        $author->books->count();
    }
    PHP));

    $findings = array_values(array_filter(
        $result['debug']['items'] ?? [],
        fn (array $item): bool => $item['kind'] === 'n_plus_one',
    ));

    expect($result['exitCode'])->toBe(0)
        ->and($findings)->toHaveCount(1)
        ->and($findings[0]['model'])->toBe('NPlusOneAuthor')
        ->and($findings[0]['relation'])->toBe('books')
        ->and($findings[0]['count'])->toBe(3)
        ->and($findings[0]['line'])->toBeInt();
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85);

it('does not flag a relation the snippet lazy-loads only once', function (): void {
    // One lazy load is a single extra query, not an N+1, so it produces no finding.
    $result = runSnippetSubprocess(nPlusOneSnippet(<<<'PHP'
    Model::automaticallyEagerLoadRelationships(false);

    NPlusOneAuthor::first()->books->count();
    PHP));

    $kinds = array_column($result['debug']['items'] ?? [], 'kind');

    expect($result['exitCode'])->toBe(0)
        ->and($kinds)->not->toContain('n_plus_one');
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85);

it('reports no n_plus_one when the project batches lazy loads with automatic eager loading', function (): void {
    // Taken as-is: a project that opts into automatic eager loading has no relation-access N+1 to
    // find, because Laravel batch-loads books for the whole set on first access. The run does not
    // override this.
    $result = runSnippetSubprocess(nPlusOneSnippet(<<<'PHP'
    Model::automaticallyEagerLoadRelationships(true);

    foreach (NPlusOneAuthor::all() as $author) {
        $author->books->count();
    }
    PHP));

    $kinds = array_column($result['debug']['items'] ?? [], 'kind');

    expect($result['exitCode'])->toBe(0)
        ->and($kinds)->not->toContain('n_plus_one');
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85);

/*
|--------------------------------------------------------------------------
| Against a committed non-Laravel fixture project (real subprocess)
|--------------------------------------------------------------------------
|
| plain-composer-php is a real Composer project with no framework and no bootstrap/app.php. It
| runs through the actual bin/run-snippet.php entry point, proving the basic pipeline end to end
| below tinkerbench's own PHP/Laravel floor, so these are not gated on PHP 8.5.
|
*/

it('captures dump and result against a plain Composer PHP fixture', function (): void {
    $result = runSnippetSubprocessAgainst(fixtureTargetPath('plain-composer-php'), <<<'PHP'
    <?php

    use PlainComposerPhp\Greeter;

    dump((new Greeter())->greet('world'));

    return ['ok' => true];
    PHP);

    expect($result['exitCode'])->toBe(0)
        ->and($result['output'])->toBe('')
        ->and(array_column($result['debug']['items'], 'kind'))->toBe(['dump', 'result'])
        ->and($result['debug']['items'][0]['html'])->toContain('Hello, world!')
        ->and($result['debug']['items'][1]['html'])->toContain('ok');
});

it('classifies the snippet frame of an uncaught exception from a plain Composer PHP fixture', function (): void {
    $result = runSnippetSubprocessAgainst(
        fixtureTargetPath('plain-composer-php'),
        "<?php\n\nthrow new RuntimeException('fixture boom');",
    );

    $item = $result['debug']['items'][0];

    expect($result['exitCode'])->toBe(0)
        ->and($item['kind'])->toBe('exception')
        ->and($item['type'])->toBe(RuntimeException::class)
        ->and($item['message'])->toBe('fixture boom')
        ->and($item['line'])->toBe(3)
        ->and($item['frames'])->toHaveCount(1)
        ->and($item['frames'][0]['snippet'])->toBeTrue()
        ->and($item['frames'][0]['line'])->toBe(3);
});

it('never emits query, log, or n_plus_one items for a plain Composer PHP fixture', function (): void {
    $result = runSnippetSubprocessAgainst(
        fixtureTargetPath('plain-composer-php'),
        "<?php\n\ndump('a');\n\nreturn 'b';",
    );

    $kinds = array_column($result['debug']['items'] ?? [], 'kind');

    expect($kinds)->not->toContain('query')
        ->and($kinds)->not->toContain('log')
        ->and($kinds)->not->toContain('n_plus_one');
});

/*
|--------------------------------------------------------------------------
| Against a committed Laravel 12 fixture (real subprocess)
|--------------------------------------------------------------------------
|
| laravel-12 is a real, minimal Laravel 12 application: the documented lower bound of the full
| (Laravel) feed. It boots on whatever PHP runs the suite (Laravel 12's own floor is PHP 8.2), so
| these prove the existing Laravel pipeline still produces the whole feed there, and are not gated
| on PHP 8.5.
|
*/

/**
 * Snippet preamble for the Laravel 12 fixture: an in-memory SQLite connection the fixture's
 * App\Models\Widget / App\Models\WidgetPart bind to, with their tables created and three
 * widget/part rows seeded.
 */
function laravel12Preamble(): string
{
    return <<<'PHP'
    <?php

    use App\Models\Widget;
    use App\Models\WidgetPart;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    config(['database.connections.fixture' => ['driver' => 'sqlite', 'database' => ':memory:']]);

    Schema::connection('fixture')->create('widgets', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
    });

    Schema::connection('fixture')->create('widget_parts', function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('widget_id');
    });

    foreach (range(1, 3) as $id) {
        Widget::create(['id' => $id, 'name' => "widget {$id}"]);
        WidgetPart::create(['id' => $id, 'widget_id' => $id]);
    }
    PHP;
}

it('captures dump, log, query, and result against a Laravel 12 fixture', function (): void {
    $result = runSnippetSubprocessAgainst(fixtureTargetPath('laravel-12'), laravel12Preamble()."\n".<<<'PHP'
    dump('from laravel 12');

    Log::info('hello from the fixture');

    $widget = Widget::query()->where('id', 2)->first();

    return $widget->name;
    PHP);

    $items = collect($result['debug']['items']);

    expect($result['exitCode'])->toBe(0)
        ->and($result['output'])->toBe('')
        ->and(array_column($result['debug']['items'], 'kind'))->toContain('dump', 'log', 'query', 'result')
        ->and($items->pluck('sql')->filter())->toContain('select * from "widgets" where "id" = 2 limit 1')
        ->and($items->firstWhere('kind', 'result')['html'])->toContain('widget 2')
        ->and($items->firstWhere('kind', 'log')['message'])->toBe('hello from the fixture')
        ->and($items->firstWhere('kind', 'dump')['html'])->toContain('from laravel 12');
});

it('classifies the snippet frame of an uncaught exception from a Laravel 12 fixture', function (): void {
    $result = runSnippetSubprocessAgainst(
        fixtureTargetPath('laravel-12'),
        "<?php\n\nthrow new RuntimeException('laravel 12 boom');",
    );

    $item = $result['debug']['items'][0];

    expect($result['exitCode'])->toBe(0)
        ->and($item['kind'])->toBe('exception')
        ->and($item['message'])->toBe('laravel 12 boom')
        ->and($item['line'])->toBe(3)
        ->and($item['frames'][0]['snippet'])->toBeTrue();
});

it('detects an N+1 lazy load against a Laravel 12 fixture', function (): void {
    $result = runSnippetSubprocessAgainst(fixtureTargetPath('laravel-12'), laravel12Preamble()."\n".<<<'PHP'
    foreach (Widget::all() as $widget) {
        $widget->parts->count();
    }
    PHP);

    $finding = collect($result['debug']['items'] ?? [])->firstWhere('kind', 'n_plus_one');

    expect($result['exitCode'])->toBe(0)
        ->and($finding)->not->toBeNull()
        ->and($finding['model'])->toBe(Widget::class)
        ->and($finding['relation'])->toBe('parts')
        ->and($finding['count'])->toBe(3);
});

// In-process runs exercise run()'s own wiring against tinkerbench itself. The shutdown handler
// it registers no-ops at PHPUnit exit because run() has already persisted inline.

function runInProcess(string $code): array
{
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    $debugPath = tempnam(sys_get_temp_dir(), 'debug');
    file_put_contents($snippetPath, $code);

    (new SnippetRunner())->run(runnerTargetPath(), $snippetPath, $debugPath);

    $snapshot = json_decode((string) file_get_contents($debugPath), true);

    unlink($snippetPath);
    unlink($debugPath);

    return is_array($snapshot) ? $snapshot : [];
}

it('records the return value of an in-process run as a result item and writes the snapshot', function (): void {
    $snapshot = runInProcess("<?php\n\nreturn 'inprocess hello';");

    expect($snapshot)->toHaveKeys(['items', 'duration_str', 'peak_memory_str'])
        ->and($snapshot['items'])->toHaveCount(1)
        ->and($snapshot['items'][0]['kind'])->toBe('result')
        ->and($snapshot['items'][0]['html'])->toContain('inprocess hello');
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85)->expectOutputString('');

it('records no result item for an in-process run with no return statement', function (): void {
    $snapshot = runInProcess("<?php\n\n\$x = 1 + 1;");

    expect($snapshot['items'])->toBe([]);
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85)->expectOutputString('');

it('records a thrown exception from an in-process run without re-throwing', function (): void {
    $snapshot = runInProcess("<?php\n\nthrow new RuntimeException('inprocess boom');");

    expect($snapshot['items'][0]['kind'])->toBe('exception')
        ->and($snapshot['items'][0]['message'])->toBe('inprocess boom');
})->skip(PHP_VERSION_ID < 80500, TARGET_REQUIRES_PHP85);

it('persist writes the snapshot and records no exception for a null last error', function (): void {
    $debugPath = tempnam(sys_get_temp_dir(), 'persist');

    $recorder = Mockery::mock(SnippetRunRecorder::class);
    $recorder->shouldReceive('snapshot')->andReturn(fixtureSnapshot());
    $recorder->shouldNotReceive('appendException');

    (new SnippetRunner())->persist($recorder, new SourceLocator('/x'), $debugPath, null);

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

    (new SnippetRunner())->persist($recorder, new SourceLocator('/x'), $debugPath, null);

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

    (new SnippetRunner())->persist(
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

    (new SnippetRunner())->persist(
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

/*
|--------------------------------------------------------------------------
| Against a non-Laravel Composer target (the basic pipeline)
|--------------------------------------------------------------------------
|
| A Composer project with no bootstrap/app.php, or one whose bootstrap/app.php does not return an
| Illuminate\Foundation\Application, gets a reduced feed (dump/result/exception) instead of a hard
| failure. These runs never boot Laravel, so they are not gated on PHP 8.5 the way the
| tinkerbench-as-target tests above are.
|
*/

/**
 * Creates a throwaway non-Laravel Composer project: a requirable vendor/autoload.php plus,
 * when $bootstrapBody is given, a bootstrap/app.php with that body.
 */
function basicComposerTarget(?string $bootstrapBody = null): string
{
    $dir = sys_get_temp_dir().'/tb-basic-target-'.bin2hex(random_bytes(6));
    mkdir($dir.'/vendor', recursive: true);
    file_put_contents($dir.'/vendor/autoload.php', "<?php\n");

    if ($bootstrapBody !== null) {
        mkdir($dir.'/bootstrap', recursive: true);
        file_put_contents($dir.'/bootstrap/app.php', $bootstrapBody);
    }

    return $dir;
}

/**
 * Runs $code in-process against a fresh non-Laravel target, then removes the target and temp
 * files and returns the decoded debug snapshot.
 *
 * @return array<string, mixed>
 */
function runBasicInProcess(string $code, ?string $bootstrapBody = null): array
{
    $target = basicComposerTarget($bootstrapBody);
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    $debugPath = tempnam(sys_get_temp_dir(), 'debug');
    file_put_contents($snippetPath, $code);

    (new SnippetRunner())->run($target, $snippetPath, $debugPath);

    $snapshot = json_decode((string) file_get_contents($debugPath), true);

    unlink($snippetPath);
    unlink($debugPath);
    @unlink($target.'/bootstrap/app.php');
    @rmdir($target.'/bootstrap');
    unlink($target.'/vendor/autoload.php');
    rmdir($target.'/vendor');
    rmdir($target);

    return is_array($snapshot) ? $snapshot : [];
}

it('captures dump and result items against a target with no bootstrap/app.php', function (): void {
    $snapshot = runBasicInProcess("<?php\n\ndump('from a plain project');\n\nreturn 'the value';");

    $kinds = array_column($snapshot['items'], 'kind');

    expect($kinds)->toBe(['dump', 'result'])
        ->and($kinds)->not->toContain('query')
        ->and($kinds)->not->toContain('log')
        ->and($kinds)->not->toContain('n_plus_one')
        ->and($snapshot['items'][0]['html'])->toContain('from a plain project')
        ->and($snapshot['items'][0]['line'])->toBe(3)
        ->and($snapshot['items'][1]['html'])->toContain('the value');
})->expectOutputString('');

it('captures an uncaught exception against a target with no bootstrap/app.php', function (): void {
    $snapshot = runBasicInProcess("<?php\n\nthrow new RuntimeException('plain boom');");

    expect($snapshot['items'])->toHaveCount(1)
        ->and($snapshot['items'][0]['kind'])->toBe('exception')
        ->and($snapshot['items'][0]['type'])->toBe(RuntimeException::class)
        ->and($snapshot['items'][0]['message'])->toBe('plain boom')
        ->and($snapshot['items'][0]['line'])->toBe(3)
        ->and($snapshot['items'][0]['frames'][0]['snippet'])->toBeTrue();
});

it('uses the basic pipeline when bootstrap/app.php does not return an Application', function (): void {
    $snapshot = runBasicInProcess(
        "<?php\n\ndump('still captured');\n\nreturn 42;",
        "<?php\n\nreturn new stdClass();",
    );

    $kinds = array_column($snapshot['items'], 'kind');

    expect($kinds)->toBe(['dump', 'result'])
        ->and($snapshot['items'][1]['html'])->toContain('42');
})->expectOutputString('');
