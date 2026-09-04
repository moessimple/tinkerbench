<?php

declare(strict_types=1);

use Tinkerbench\Runner\FeedItems\LogFeedItem;

it('serializes to the log feed-item shape', function (): void {
    $item = new LogFeedItem('warning', 'disk almost full', '<tree/>', 'array:1 [ …');
    $item->line = 7;

    expect($item->toArray())->toBe([
        'kind' => 'log',
        'label' => 'warning',
        'message' => 'disk almost full',
        'context_html' => '<tree/>',
        'context_text' => 'array:1 [ …',
        'line' => 7,
    ]);
});

it('serializes a null context when there is none', function (): void {
    $shape = (new LogFeedItem('info', 'started', null, null))->toArray();

    expect($shape['context_html'])->toBeNull()
        ->and($shape['context_text'])->toBeNull();
});
