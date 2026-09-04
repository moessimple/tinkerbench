<?php

declare(strict_types=1);

use App\Support\FeedItems\QueryFeedItem;

it('serializes to the query feed-item shape', function (): void {
    $item = new QueryFeedItem('select * from "users" where "id" = 5', 4.2, 'sqlite');
    $item->line = 12;

    expect($item->toArray())->toBe([
        'kind' => 'query',
        'sql' => 'select * from "users" where "id" = 5',
        'duration_str' => '4.20ms',
        'duration_ms' => 4.2,
        'connection' => 'sqlite',
        'slow' => false,
        'duplicate' => false,
        'line' => 12,
    ]);
});

it('flags a query at or over 100ms as slow', function (): void {
    expect(new QueryFeedItem('select 1', 100.0, 'sqlite')->toArray()['slow'])->toBeTrue()
        ->and(new QueryFeedItem('select 1', 99.9, 'sqlite')->toArray()['slow'])->toBeFalse();
});

it('reports the duplicate flag once it is set', function (): void {
    $item = new QueryFeedItem('select 1', 1.0, 'sqlite');
    $item->duplicate = true;

    expect($item->toArray()['duplicate'])->toBeTrue();
});
