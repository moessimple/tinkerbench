<?php

declare(strict_types=1);

use App\Support\FeedItems\DumpFeedItem;

it('serializes to the dump feed-item shape', function (): void {
    $item = new DumpFeedItem('<pre>x</pre>');
    $item->line = 3;

    expect($item->toArray())->toBe([
        'kind' => 'dump',
        'html' => '<pre>x</pre>',
        'line' => 3,
    ]);
});

it('serializes a null line when none was stamped', function (): void {
    expect(new DumpFeedItem('<a/>')->toArray()['line'])->toBeNull();
});
