<?php

declare(strict_types=1);

use Tinkerbench\Runner\FeedItems\FeedItem;

function feedItemStub(): FeedItem
{
    return new class extends FeedItem
    {
        public function toArray(): array
        {
            return ['kind' => 'stub', 'line' => $this->line];
        }
    };
}

it('starts with no line', function (): void {
    expect(feedItemStub()->line)->toBeNull();
});

it('carries a line once one is stamped on it', function (): void {
    $item = feedItemStub();
    $item->line = 42;

    expect($item->toArray())->toBe(['kind' => 'stub', 'line' => 42]);
});
