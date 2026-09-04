<?php

declare(strict_types=1);

use App\Support\FeedItems\FeedItem;
use App\Support\FeedItems\LogFeedItem;
use App\Support\Watchers\LogWatcher;
use Illuminate\Log\Events\MessageLogged;

it('emits a log item built from the logged message, without a line of its own', function (): void {
    $emitted = [];

    new LogWatcher()->register(app(), function (FeedItem $item) use (&$emitted): void {
        $emitted[] = $item;
    });

    event(new MessageLogged('warning', 'disk almost full', ['free' => '2%']));

    expect($emitted)->toHaveCount(1)
        ->and($emitted[0])->toBeInstanceOf(LogFeedItem::class)
        ->and($emitted[0]->toArray())->toBe([
            'kind' => 'log',
            'label' => 'warning',
            'message' => 'disk almost full',
            'context' => '{"free":"2%"}',
            'line' => null,
        ]);
});
