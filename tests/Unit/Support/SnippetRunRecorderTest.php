<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\ExceptionMapper;
use App\Support\SnippetRunRecorder;
use App\Support\Watchers\Watcher;
use Illuminate\Contracts\Foundation\Application;

/**
 * Runs a recorder with one stub watcher that hands its emit callback straight to $run, so a test
 * drives item capture without real dump/query/log events.
 *
 * @param  Closure(callable): void  $run
 */
function runRecorder(Closure $run, ?ExceptionMapper $mapper = null): SnippetRunRecorder
{
    $emit = null;

    $watcher = Mockery::mock(Watcher::class);
    $watcher->shouldReceive('register')->andReturnUsing(function ($app, callable $given) use (&$emit): void {
        $emit = $given;
    });

    $recorder = new SnippetRunRecorder([$watcher], $mapper ?? Mockery::mock(ExceptionMapper::class));

    $recorder->record(Mockery::mock(Application::class), function () use (&$emit, $run): void {
        $run($emit);
    });

    return $recorder;
}

it('collects emitted items in order and assembles a snapshot', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(['kind' => 'dump', 'html' => '<a/>', 'line' => 1]);
        $emit(['kind' => 'log', 'label' => 'info', 'message' => 'hi', 'context' => null, 'line' => 2]);
    });

    $snapshot = $recorder->snapshot();

    expect($snapshot['items'])->toBe([
        ['kind' => 'dump', 'html' => '<a/>', 'line' => 1],
        ['kind' => 'log', 'label' => 'info', 'message' => 'hi', 'context' => null, 'line' => 2],
    ])
        ->and($snapshot['duration_str'])->toMatch('/^\d+\.\d{2}(ms|s)$/')
        ->and($snapshot['peak_memory_str'])->toMatch('/^[\d,]+\.\d{2} MB$/');
});

it('flags an identical repeated query as a duplicate, leaving the first untouched', function (): void {
    $query = fn (string $sql): array => [
        'kind' => 'query', 'sql' => $sql, 'duration_str' => '1.00ms',
        'connection' => 'sqlite', 'slow' => false, 'duplicate' => false, 'line' => null,
    ];

    $recorder = runRecorder(function (callable $emit) use ($query): void {
        $emit($query('select * from users where id = 1'));
        $emit($query('select * from users where id = 1'));
        $emit($query('select * from users where id = 2'));
    });

    $items = $recorder->snapshot()['items'];

    expect($items[0]['duplicate'])->toBeFalse()
        ->and($items[1]['duplicate'])->toBeTrue()
        ->and($items[2]['duplicate'])->toBeFalse();
});

it('folds repeated lazy-loads of the same relation into one item with a count', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(['kind' => 'query', 'sql' => 'select * from users', 'duration_str' => '1.00ms', 'connection' => 'sqlite', 'slow' => false, 'duplicate' => false, 'line' => 1]);
        $emit(['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'posts', 'line' => 4]);
        $emit(['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'posts', 'line' => 9]);
    });

    expect($recorder->snapshot()['items'])->toBe([
        ['kind' => 'query', 'sql' => 'select * from users', 'duration_str' => '1.00ms', 'connection' => 'sqlite', 'slow' => false, 'duplicate' => false, 'line' => 1],
        ['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'posts', 'line' => 4, 'count' => 2],
    ]);
});

it('keeps a separate count per relation on the same model', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'posts', 'line' => 4]);
        $emit(['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'comments', 'line' => 5]);
        $emit(['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'posts', 'line' => 4]);
        $emit(['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'comments', 'line' => 5]);
    });

    expect($recorder->snapshot()['items'])->toBe([
        ['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'posts', 'line' => 4, 'count' => 2],
        ['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'comments', 'line' => 5, 'count' => 2],
    ]);
});

it('drops a relation lazy-loaded only once, since a single lazy load is not an N+1', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'posts', 'line' => 4]);
        $emit(['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'comments', 'line' => 5]);
        $emit(['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'comments', 'line' => 5]);
    });

    expect($recorder->snapshot()['items'])->toBe([
        ['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'comments', 'line' => 5, 'count' => 2],
    ]);
});

it('does not let lazy-load folding touch the duplicate-query bookkeeping', function (): void {
    $query = fn (string $sql): array => [
        'kind' => 'query', 'sql' => $sql, 'duration_str' => '1.00ms',
        'connection' => 'sqlite', 'slow' => false, 'duplicate' => false, 'line' => null,
    ];

    $recorder = runRecorder(function (callable $emit) use ($query): void {
        $emit($query('select * from posts where user_id = 1'));
        $emit(['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'posts', 'line' => 4]);
        $emit($query('select * from posts where user_id = 1'));
        $emit(['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'posts', 'line' => 4]);
    });

    $items = $recorder->snapshot()['items'];

    expect($items)->toHaveCount(3)
        ->and($items[0]['duplicate'])->toBeFalse()
        ->and($items[1])->toBe(['kind' => 'n_plus_one', 'model' => User::class, 'relation' => 'posts', 'line' => 4, 'count' => 2])
        ->and($items[2]['kind'])->toBe('query')
        ->and($items[2]['duplicate'])->toBeTrue();
});

it('reports a zero duration when snapshot is taken before a run', function (): void {
    $recorder = new SnippetRunRecorder([], Mockery::mock(ExceptionMapper::class));

    $snapshot = $recorder->snapshot();

    expect($snapshot['items'])->toBe([])
        ->and($snapshot['duration_str'])->toBe('0.00ms');
});

it('appends an exception item mapped from the throwable', function (): void {
    $throwable = new RuntimeException('boom');
    $mapped = ['kind' => 'exception', 'type' => RuntimeException::class, 'message' => 'boom', 'line' => 5, 'frames' => []];

    $mapper = Mockery::mock(ExceptionMapper::class);
    $mapper->shouldReceive('toItem')->once()->with($throwable, 5, true)->andReturn($mapped);

    $recorder = runRecorder(function (callable $emit): void {
        $emit(['kind' => 'dump', 'html' => '<a/>', 'line' => 1]);
    }, $mapper);

    $recorder->appendException($throwable, 5);

    expect($recorder->snapshot()['items'])->toBe([
        ['kind' => 'dump', 'html' => '<a/>', 'line' => 1],
        $mapped,
    ]);
});

it('appends the rendered return value as a result item after the captured items', function (): void {
    $recorder = runRecorder(function (callable $emit): void {
        $emit(['kind' => 'dump', 'html' => '<a/>', 'line' => 1]);
    });

    $recorder->appendResult('<pre>the value</pre>');

    expect($recorder->snapshot()['items'])->toBe([
        ['kind' => 'dump', 'html' => '<a/>', 'line' => 1],
        ['kind' => 'result', 'html' => '<pre>the value</pre>'],
    ]);
});

it('forwards a request to omit frames to the mapper', function (): void {
    $throwable = new RuntimeException('boom');

    $mapper = Mockery::mock(ExceptionMapper::class);
    $mapper->shouldReceive('toItem')->once()->with($throwable, null, false)->andReturn(['kind' => 'exception']);

    $recorder = runRecorder(function (callable $emit): void {}, $mapper);

    $recorder->appendException($throwable, null, false);

    expect($recorder->snapshot()['items'])->toBe([['kind' => 'exception']]);
});
