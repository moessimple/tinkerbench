<?php

declare(strict_types=1);

namespace Tinkerbench\Runner\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Tinkerbench\Runner\DumpCapture;
use Tinkerbench\Runner\FeedItems\DumpFeedItem;
use Tinkerbench\Runner\ValueRenderer;

class DumpWatcher implements Watcher
{
    public function __construct(private readonly ValueRenderer $renderer) {}

    public function register(Application $app, callable $emit): void
    {
        DumpCapture::install(
            $this->renderer,
            fn (string $html, string $text) => $emit(new DumpFeedItem($html, $text)),
        );
    }
}
