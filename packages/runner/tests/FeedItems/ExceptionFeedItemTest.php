<?php

declare(strict_types=1);

use Tinkerbench\Runner\FeedItems\ExceptionFeedItem;

it('serializes to the exception feed-item shape', function (): void {
    $frames = [['file' => '/x', 'line' => 3, 'function' => 'f', 'vendor' => false, 'snippet' => true]];
    $item = new ExceptionFeedItem(RuntimeException::class, 'boom', $frames);
    $item->line = 7;

    expect($item->toArray())->toBe([
        'kind' => 'exception',
        'type' => RuntimeException::class,
        'message' => 'boom',
        'line' => 7,
        'frames' => $frames,
    ]);
});

it('serializes with no line and no frames', function (): void {
    expect((new ExceptionFeedItem(RuntimeException::class, 'boom', []))->toArray())->toBe([
        'kind' => 'exception',
        'type' => RuntimeException::class,
        'message' => 'boom',
        'line' => null,
        'frames' => [],
    ]);
});
