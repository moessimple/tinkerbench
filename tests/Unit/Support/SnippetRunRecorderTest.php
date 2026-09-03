<?php

declare(strict_types=1);

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

it('forwards a request to omit frames to the mapper', function (): void {
    $throwable = new RuntimeException('boom');

    $mapper = Mockery::mock(ExceptionMapper::class);
    $mapper->shouldReceive('toItem')->once()->with($throwable, null, false)->andReturn(['kind' => 'exception']);

    $recorder = runRecorder(function (callable $emit): void {}, $mapper);

    $recorder->appendException($throwable, null, false);

    expect($recorder->snapshot()['items'])->toBe([['kind' => 'exception']]);
});
