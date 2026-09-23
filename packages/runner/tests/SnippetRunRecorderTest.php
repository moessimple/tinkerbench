<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Tinkerbench\Runner\Duration;
use Tinkerbench\Runner\ExceptionMapper;
use Tinkerbench\Runner\FeedItems\DumpFeedItem;
use Tinkerbench\Runner\FeedItems\ExceptionFeedItem;
use Tinkerbench\Runner\FeedItems\HttpClientFeedItem;
use Tinkerbench\Runner\FeedItems\LogFeedItem;
use Tinkerbench\Runner\FeedItems\NPlusOneFeedItem;
use Tinkerbench\Runner\FeedItems\QueryFeedItem;
use Tinkerbench\Runner\SnippetRunRecorder;
use Tinkerbench\Runner\SourceLocator;
use Tinkerbench\Runner\Watchers\Watcher;

/**
 * Runs a recorder with one stub watcher that hands its emit callback straight to $run, so a test
 * drives item capture without real dump/query/log events. Unless a test passes its own SourceLocator,
 * every emitted item is attributed to line 99.
 *
 * @param  Closure(callable): void  $run
 */
function runRecorder(Closure $run, ?ExceptionMapper $mapper = null, ?SourceLocator $source = null, ?float $runStartedAt = null): SnippetRunRecorder
{
    $emit = null;

    $watcher = Mockery::mock(Watcher::class);
    $watcher->shouldReceive('register')->andReturnUsing(function ($app, callable $given) use (&$emit): void {
        $emit = $given;
    });

    if (! $source instanceof SourceLocator) {
        $source = Mockery::mock(SourceLocator::class);
        $source->shouldReceive('snippetLine')->andReturn(99);
    }

    $recorder = new SnippetRunRecorder([$watcher], $mapper ?? Mockery::mock(ExceptionMapper::class), $source, $runStartedAt ?? hrtime(true));

    $recorder->record(Mockery::mock(Application::class), function () use (&$emit, $run): void {
        $run($emit);
    });

    return $recorder;
}

function recordedHttpCall(float $startedAtMs, float $durationMs): HttpClientFeedItem
{
    return new HttpClientFeedItem('GET', 'https://example.test', false, 200, $startedAtMs * 1_000_000, $durationMs, [], [], '', null, null, '', null, null);
}

/**
 * Milliseconds as an integer count of hundredths, so sums of two-decimal values compare exactly
 * instead of through float addition.
 */
function hundredths(float $milliseconds): int
{
    return (int) round($milliseconds * 100);
}

it('collects emitted items in order and assembles a snapshot', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(new DumpFeedItem('<a/>', 'a'));
        $emit(new LogFeedItem('info', 'hi', null, null));
    });

    $snapshot = $recorder->snapshot();

    expect($snapshot['items'])->toBe([
        ['kind' => 'dump', 'html' => '<a/>', 'text' => 'a', 'line' => 99],
        ['kind' => 'log', 'label' => 'info', 'message' => 'hi', 'context_html' => null, 'context_text' => null, 'line' => 99],
    ])
        ->and($snapshot['run_duration_str'])->toMatch('/^\d+\.\d{2}(ms|s)$/')
        ->and($snapshot['peak_memory_str'])->toMatch('/^[\d,]+\.\d{2} MB$/');
});

it('stamps each emitted item with the line the source locator resolves', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(3, 8);

    $recorder = runRecorder(function (callable $emit): void {
        $emit(new DumpFeedItem('<a/>', 'a'));
        $emit(new DumpFeedItem('<b/>', 'b'));
    }, source: $source);

    expect($recorder->snapshot()['items'])->toBe([
        ['kind' => 'dump', 'html' => '<a/>', 'text' => 'a', 'line' => 3],
        ['kind' => 'dump', 'html' => '<b/>', 'text' => 'b', 'line' => 8],
    ]);
});

it('leaves the line null when the source has no snippet frame', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(null);

    $recorder = runRecorder(function (callable $emit): void {
        $emit(new DumpFeedItem('<a/>', 'a'));
    }, source: $source);

    expect($recorder->snapshot()['items'][0]['line'])->toBeNull();
});

it('flags an identical repeated query as a duplicate, leaving the first untouched', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(new QueryFeedItem('select * from users where id = 1', 1.0, 'sqlite'));
        $emit(new QueryFeedItem('select * from users where id = 1', 1.0, 'sqlite'));
        $emit(new QueryFeedItem('select * from users where id = 2', 1.0, 'sqlite'));
    });

    $items = $recorder->snapshot()['items'];

    expect($items[0]['duplicate'])->toBeFalse()
        ->and($items[1]['duplicate'])->toBeTrue()
        ->and($items[2]['duplicate'])->toBeFalse();
});

it('folds repeated lazy-loads of the same relation into one item with a count', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(new QueryFeedItem('select * from users', 1.0, 'sqlite'));
        $emit(new NPlusOneFeedItem('Some\Fixture\Model', 'posts'));
        $emit(new NPlusOneFeedItem('Some\Fixture\Model', 'posts'));
    });

    expect($recorder->snapshot()['items'])->toBe([
        ['kind' => 'query', 'sql' => 'select * from users', 'duration_str' => '1.00ms', 'duration_ms' => 1.0, 'connection' => 'sqlite', 'slow' => false, 'duplicate' => false, 'line' => 99],
        ['kind' => 'n_plus_one', 'model' => 'Some\Fixture\Model', 'relation' => 'posts', 'count' => 2, 'line' => 99],
    ]);
});

it('keeps a separate count per relation on the same model', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(new NPlusOneFeedItem('Some\Fixture\Model', 'posts'));
        $emit(new NPlusOneFeedItem('Some\Fixture\Model', 'comments'));
        $emit(new NPlusOneFeedItem('Some\Fixture\Model', 'posts'));
        $emit(new NPlusOneFeedItem('Some\Fixture\Model', 'comments'));
    });

    expect($recorder->snapshot()['items'])->toBe([
        ['kind' => 'n_plus_one', 'model' => 'Some\Fixture\Model', 'relation' => 'posts', 'count' => 2, 'line' => 99],
        ['kind' => 'n_plus_one', 'model' => 'Some\Fixture\Model', 'relation' => 'comments', 'count' => 2, 'line' => 99],
    ]);
});

it('drops a relation lazy-loaded only once, since a single lazy load is not an N+1', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(new NPlusOneFeedItem('Some\Fixture\Model', 'posts'));
        $emit(new NPlusOneFeedItem('Some\Fixture\Model', 'comments'));
        $emit(new NPlusOneFeedItem('Some\Fixture\Model', 'comments'));
    });

    expect($recorder->snapshot()['items'])->toBe([
        ['kind' => 'n_plus_one', 'model' => 'Some\Fixture\Model', 'relation' => 'comments', 'count' => 2, 'line' => 99],
    ]);
});

it('does not let lazy-load folding touch the duplicate-query bookkeeping', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(new QueryFeedItem('select * from posts where user_id = 1', 1.0, 'sqlite'));
        $emit(new NPlusOneFeedItem('Some\Fixture\Model', 'posts'));
        $emit(new QueryFeedItem('select * from posts where user_id = 1', 1.0, 'sqlite'));
        $emit(new NPlusOneFeedItem('Some\Fixture\Model', 'posts'));
    });

    $items = $recorder->snapshot()['items'];

    expect($items)->toHaveCount(3)
        ->and($items[0]['duplicate'])->toBeFalse()
        ->and($items[1])->toBe(['kind' => 'n_plus_one', 'model' => 'Some\Fixture\Model', 'relation' => 'posts', 'count' => 2, 'line' => 99])
        ->and($items[2]['kind'])->toBe('query')
        ->and($items[2]['duplicate'])->toBeTrue();
});

it('sums the query durations and counts the queries of a run', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(new QueryFeedItem('select * from users', 1.25, 'sqlite'));
        $emit(new QueryFeedItem('select * from posts', 2.5, 'sqlite'));
    });

    $snapshot = $recorder->snapshot();

    expect($snapshot['query_count'])->toBe(2)
        ->and($snapshot['query_duration_ms'])->toBe(3.75)
        ->and($snapshot['query_duration_str'])->toBe('3.75ms');
});

it('counts the duplicate queries of a run', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(new QueryFeedItem('select * from users', 1.0, 'sqlite'));
        $emit(new QueryFeedItem('select * from users', 1.0, 'sqlite'));
        $emit(new QueryFeedItem('select * from users', 1.0, 'sqlite'));
        $emit(new QueryFeedItem('select * from posts', 1.0, 'sqlite'));
    });

    expect($recorder->snapshot()['duplicate_query_count'])->toBe(2);
});

it('sums the durations of sequential http client requests and counts the requests of a run', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(recordedHttpCall(0.0, 40.0));
        $emit(recordedHttpCall(50.0, 2.5));
    });

    $snapshot = $recorder->snapshot();

    expect($snapshot['http_request_count'])->toBe(2)
        ->and($snapshot['http_duration_ms'])->toBe(42.5)
        ->and($snapshot['http_duration_str'])->toBe('42.50ms');
});

it('counts the time of overlapping http client requests once', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(recordedHttpCall(50.0, 20.0));
        $emit(recordedHttpCall(0.0, 200.0));
        $emit(recordedHttpCall(10.0, 200.0));
        $emit(recordedHttpCall(300.0, 5.0));
    });

    $snapshot = $recorder->snapshot();

    expect($snapshot['http_request_count'])->toBe(4)
        ->and($snapshot['http_duration_ms'])->toBe(215.0);
});

it('splits the snippet time exactly into query, http, and php time after rounding', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(new QueryFeedItem('select * from users', 0.334, 'sqlite'));
        $emit(new QueryFeedItem('select * from posts', 0.333, 'sqlite'));
        $emit(recordedHttpCall(0.0, 0.125));
    });

    $snapshot = $recorder->snapshot();

    expect($snapshot['query_duration_ms'])->toBe(0.67)
        ->and($snapshot['http_duration_ms'])->toBe(0.13)
        ->and(hundredths($snapshot['run_duration_ms']) - hundredths($snapshot['boot_duration_ms']))->toBe(
            hundredths($snapshot['query_duration_ms']) + hundredths($snapshot['http_duration_ms']) + hundredths($snapshot['php_duration_ms']),
        )
        ->and($snapshot['php_duration_str'])->toBe(Duration::format($snapshot['php_duration_ms']));
});

it('attributes the whole snippet duration to php time when the run made no queries or http calls', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(new DumpFeedItem('<a/>', 'a'));
    });

    $snapshot = $recorder->snapshot();

    expect($snapshot['query_count'])->toBe(0)
        ->and($snapshot['duplicate_query_count'])->toBe(0)
        ->and($snapshot['query_duration_ms'])->toBe(0.0)
        ->and($snapshot['http_request_count'])->toBe(0)
        ->and($snapshot['http_duration_ms'])->toBe(0.0)
        ->and(hundredths($snapshot['php_duration_ms']))->toBe(hundredths($snapshot['run_duration_ms']) - hundredths($snapshot['boot_duration_ms']));
});

it('reports a zero duration when snapshot is taken before a run', function (): void {
    $recorder = new SnippetRunRecorder([], Mockery::mock(ExceptionMapper::class), Mockery::mock(SourceLocator::class), hrtime(true));

    $snapshot = $recorder->snapshot();

    expect($snapshot['items'])->toBe([])
        ->and($snapshot['run_duration_str'])->toBe('0.00ms')
        ->and($snapshot['boot_duration_ms'])->toBe(0.0)
        ->and($snapshot['run_duration_ms'])->toBe(0.0);
});

it('measures the boot time from the run start up to the start of the snippet', function (): void {
    $fiveMillisecondsAgo = hrtime(true) - 5_000_000;

    $recorder = runRecorder(function (callable $emit): void {}, runStartedAt: $fiveMillisecondsAgo);

    $snapshot = $recorder->snapshot();

    expect($snapshot['boot_duration_ms'])->toBeGreaterThanOrEqual(5.0)
        ->and($snapshot['boot_duration_str'])->toBe(Duration::format($snapshot['boot_duration_ms']));
});

it('adds the boot and snippet time up to the run time exactly after rounding', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(new QueryFeedItem('select * from users', 0.334, 'sqlite'));
    }, runStartedAt: hrtime(true) - 1_234_567);

    $snapshot = $recorder->snapshot();

    expect(hundredths($snapshot['run_duration_ms']))->toBe(
        hundredths($snapshot['boot_duration_ms']) + hundredths($snapshot['query_duration_ms']) + hundredths($snapshot['php_duration_ms']),
    )
        ->and($snapshot['run_duration_str'])->toBe(Duration::format($snapshot['run_duration_ms']));
});

it('appends an exception item mapped from the throwable', function (): void {
    $throwable = new RuntimeException('boom');
    $mapped = new ExceptionFeedItem(RuntimeException::class, 'boom', []);
    $mapped->line = 5;

    $mapper = Mockery::mock(ExceptionMapper::class);
    $mapper->shouldReceive('toItem')->once()->with($throwable, 5, true)->andReturn($mapped);

    $recorder = runRecorder(function (callable $emit): void {
        $emit(new DumpFeedItem('<a/>', 'a'));
    }, $mapper);

    $recorder->appendException($throwable, 5);

    expect($recorder->snapshot()['items'])->toBe([
        ['kind' => 'dump', 'html' => '<a/>', 'text' => 'a', 'line' => 99],
        ['kind' => 'exception', 'type' => RuntimeException::class, 'message' => 'boom', 'line' => 5, 'frames' => []],
    ]);
});

it('appends the rendered return value as a result item after the captured items', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(new DumpFeedItem('<a/>', 'a'));
    });

    $recorder->appendResult('<pre>the value</pre>', 'the value');

    expect($recorder->snapshot()['items'])->toBe([
        ['kind' => 'dump', 'html' => '<a/>', 'text' => 'a', 'line' => 99],
        ['kind' => 'result', 'html' => '<pre>the value</pre>', 'text' => 'the value'],
    ]);
});

it('appends a dump item stamped with the resolved snippet line', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(7);

    $recorder = new SnippetRunRecorder([], Mockery::mock(ExceptionMapper::class), $source, hrtime(true));

    $recorder->appendDump('<a/>', 'a');

    expect($recorder->snapshot()['items'])->toBe([
        ['kind' => 'dump', 'html' => '<a/>', 'text' => 'a', 'line' => 7],
    ]);
});

it('registers no watchers when record is given no application', function (): void {
    $watcher = Mockery::mock(Watcher::class);
    $watcher->shouldNotReceive('register');

    $recorder = new SnippetRunRecorder([$watcher], Mockery::mock(ExceptionMapper::class), Mockery::mock(SourceLocator::class), hrtime(true));

    $ran = false;
    $recorder->record(null, function () use (&$ran): void {
        $ran = true;
    });

    expect($ran)->toBeTrue()
        ->and($recorder->snapshot()['run_duration_str'])->toMatch('/^\d+\.\d{2}(ms|s)$/');
});

it('forwards a request to omit frames to the mapper', function (): void {
    $throwable = new RuntimeException('boom');

    $mapper = Mockery::mock(ExceptionMapper::class);
    $mapper->shouldReceive('toItem')->once()->with($throwable, null, false)->andReturn(new ExceptionFeedItem(RuntimeException::class, 'boom', []));

    $recorder = runRecorder(function (callable $emit): void {}, $mapper);

    $recorder->appendException($throwable, null, false);

    expect($recorder->snapshot()['items'])->toBe([
        ['kind' => 'exception', 'type' => RuntimeException::class, 'message' => 'boom', 'line' => null, 'frames' => []],
    ]);
});
