<?php

declare(strict_types=1);

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
    $snippetPath = tempnam(sys_get_temp_dir(), 'snippet').'.php';
    $debugPath = tempnam(sys_get_temp_dir(), 'debug');
    file_put_contents($snippetPath, $code);

    $result = Process::env(['VAR_DUMPER_FORMAT' => 'html'])->run([
        PHP_BINARY,
        dirname(__DIR__).'/bin/run-snippet.php',
        runnerTargetPath(),
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
