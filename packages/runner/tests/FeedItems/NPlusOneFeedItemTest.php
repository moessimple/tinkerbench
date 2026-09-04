<?php

declare(strict_types=1);

use Tinkerbench\Runner\FeedItems\NPlusOneFeedItem;

it('serializes to the n_plus_one feed-item shape', function (): void {
    $item = new NPlusOneFeedItem('Some\Fixture\Model', 'posts');
    $item->line = 4;

    expect($item->toArray())->toBe([
        'kind' => 'n_plus_one',
        'model' => 'Some\Fixture\Model',
        'relation' => 'posts',
        'count' => 1,
        'line' => 4,
    ]);
});

it('reflects an incremented count', function (): void {
    $item = new NPlusOneFeedItem('Some\Fixture\Model', 'posts');
    $item->count++;

    expect($item->toArray()['count'])->toBe(2);
});
