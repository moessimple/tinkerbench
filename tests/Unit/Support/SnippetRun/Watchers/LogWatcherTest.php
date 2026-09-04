<?php

declare(strict_types=1);

use App\Support\SnippetRun\FeedItems\FeedItem;
use App\Support\SnippetRun\FeedItems\LogFeedItem;
use App\Support\SnippetRun\Watchers\LogWatcher;
use Illuminate\Log\Events\MessageLogged;

it('emits a log item built from the logged message, without a line of its own', function (): void {
    $emitted = [];

    new LogWatcher()->register(app(), function (FeedItem $item) use (&$emitted): void {
        $emitted[] = $item;
    });

    event(new MessageLogged('warning', 'disk almost full', ['free' => '2%']));

    expect($emitted)->toHaveCount(1)
        ->and($emitted[0])->toBeInstanceOf(LogFeedItem::class);

    $shape = $emitted[0]->toArray();

    expect($shape)->toMatchArray([
        'kind' => 'log',
        'label' => 'warning',
        'message' => 'disk almost full',
        'line' => null,
    ])
        ->and($shape['context_text'])->toContain('2%');
});
