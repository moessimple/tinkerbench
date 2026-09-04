<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tinkerbench\Runner\FeedItems\FeedItem;
use Tinkerbench\Runner\FeedItems\QueryFeedItem;
use Tinkerbench\Runner\Watchers\QueryWatcher;

/**
 * @return list<FeedItem>
 */
function captureQueryItems(QueryExecuted $query): array
{
    $emitted = [];

    (new QueryWatcher())->register(app(), function (FeedItem $item) use (&$emitted): void {
        $emitted[] = $item;
    });

    event($query);

    return $emitted;
}

it('emits a query item built from the executed query, without a line of its own', function (): void {
    $query = new QueryExecuted('select * from "users" where "id" = ?', [5], 4.2, DB::connection());

    $items = captureQueryItems($query);

    expect($items)->toHaveCount(1)
        ->and($items[0])->toBeInstanceOf(QueryFeedItem::class)
        ->and($items[0]->toArray())->toBe([
            'kind' => 'query',
            'sql' => 'select * from "users" where "id" = 5',
            'duration_str' => '4.20ms',
            'duration_ms' => 4.2,
            'connection' => DB::connection()->getName(),
            'slow' => false,
            'duplicate' => false,
            'line' => null,
        ]);
});
