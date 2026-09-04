<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\FeedItems\NPlusOneFeedItem;

it('serializes to the n_plus_one feed-item shape', function (): void {
    $item = new NPlusOneFeedItem(User::class, 'posts');
    $item->line = 4;

    expect($item->toArray())->toBe([
        'kind' => 'n_plus_one',
        'model' => User::class,
        'relation' => 'posts',
        'count' => 1,
        'line' => 4,
    ]);
});

it('reflects an incremented count', function (): void {
    $item = new NPlusOneFeedItem(User::class, 'posts');
    $item->count++;

    expect($item->toArray()['count'])->toBe(2);
});
