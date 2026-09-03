<?php

declare(strict_types=1);

namespace App\Support\Watchers;

use Illuminate\Contracts\Foundation\Application;

/**
 * A source of feed items captured while a snippet runs. Each watcher hooks whatever it listens to
 * (a framework event, a global handler) and pushes typed item arrays into the run through $emit.
 * SnippetRunRecorder owns the list of watchers, so adding a capture kind is a new implementation
 * here plus a matching FeedItem variant on the frontend, nothing else.
 */
interface Watcher
{
    /**
     * @param  callable(array<string, mixed>): void  $emit
     */
    public function register(Application $app, callable $emit): void;
}
