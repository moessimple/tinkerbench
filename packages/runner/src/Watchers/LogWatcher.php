<?php

declare(strict_types=1);

namespace Tinkerbench\Runner\Watchers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;
use Tinkerbench\Runner\FeedItems\LogFeedItem;
use Tinkerbench\Runner\ValueRenderer;

class LogWatcher implements Watcher
{
    public function __construct(private ValueRenderer $renderer) {}

    public function register(Application $app, callable $emit): void
    {
        $app->make(Dispatcher::class)->listen(MessageLogged::class, function (MessageLogged $event) use ($emit): void {
            $hasContext = $event->context !== [];

            $emit(new LogFeedItem(
                $event->level,
                $event->message,
                $hasContext ? $this->renderer->render($event->context) : null,
                $hasContext ? $this->renderer->renderText($event->context) : null,
            ));
        });
    }
}
