<?php

declare(strict_types=1);

use App\Support\SourceLocator;
use App\Support\Watchers\LogWatcher;
use Illuminate\Log\Events\MessageLogged;

/**
 * @return list<array<string, mixed>>
 */
function captureLogItems(SourceLocator $source, MessageLogged $event): array
{
    $emitted = [];

    new LogWatcher($source)->register(app(), function (array $item) use (&$emitted): void {
        $emitted[] = $item;
    });

    event($event);

    return $emitted;
}

it('emits a log item with the documented shape', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(7);

    $items = captureLogItems($source, new MessageLogged('warning', 'disk almost full', ['free' => '2%']));

    expect($items)->toHaveCount(1)
        ->and($items[0])->toBe([
            'kind' => 'log',
            'label' => 'warning',
            'message' => 'disk almost full',
            'context' => '{"free":"2%"}',
            'line' => 7,
        ]);
});

it('sets context to null when the log carries none', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(1);

    $items = captureLogItems($source, new MessageLogged('info', 'started', []));

    expect($items[0]['context'])->toBeNull();
});

it('leaves the line null when the source has no snippet frame', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(null);

    $items = captureLogItems($source, new MessageLogged('debug', 'x', []));

    expect($items[0]['line'])->toBeNull();
});
