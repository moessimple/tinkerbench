<?php

declare(strict_types=1);

use App\Support\SnippetRun\FeedItems\LogFeedItem;

it('serializes to the log feed-item shape with the context JSON-encoded', function (): void {
    $item = new LogFeedItem('warning', 'disk almost full', ['free' => '2%']);
    $item->line = 7;

    expect($item->toArray())->toBe([
        'kind' => 'log',
        'label' => 'warning',
        'message' => 'disk almost full',
        'context' => '{"free":"2%"}',
        'line' => 7,
    ]);
});

it('encodes an empty context as null', function (): void {
    expect(new LogFeedItem('info', 'started', [])->toArray()['context'])->toBeNull();
});

it('encodes an unencodable context as null', function (): void {
    expect(new LogFeedItem('info', 'x', ['bad' => "\xB1\x31"])->toArray()['context'])->toBeNull();
});
