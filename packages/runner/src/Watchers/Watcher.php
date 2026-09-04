<?php

declare(strict_types=1);

namespace Tinkerbench\Runner\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Tinkerbench\Runner\FeedItems\FeedItem;

/**
 * A source of feed items captured while a snippet runs. Each watcher hooks whatever it listens to
 * (a framework event, a global handler) and pushes FeedItem objects into the run through $emit.
 * SnippetRunRecorder owns the list of watchers and stamps each emitted item's snippet line, so
 * adding a capture kind is a new watcher here plus a matching FeedItem subclass, nothing else.
 */
interface Watcher
{
    /**
     * @param  callable(FeedItem): void  $emit
     */
    public function register(Application $app, callable $emit): void;
}
