<?php

declare(strict_types=1);

namespace App\Support\SnippetRun\Watchers;

use App\Support\SnippetRun\FeedItems\LogFeedItem;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;

class LogWatcher implements Watcher
{
    public function register(Application $app, callable $emit): void
    {
        $app->make(Dispatcher::class)->listen(MessageLogged::class, function (MessageLogged $event) use ($emit): void {
            $emit(new LogFeedItem($event->level, $event->message, $event->context));
        });
    }
}
