<?php

declare(strict_types=1);

namespace App\Support\SnippetRun\Watchers;

use App\Support\SnippetRun\FeedItems\DumpFeedItem;
use App\Support\SnippetRun\ValueRenderer;
use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\VarDumper\VarDumper;

class DumpWatcher implements Watcher
{
    public function __construct(private ValueRenderer $renderer) {}

    public function register(Application $app, callable $emit): void
    {
        // Herd::runSnippet() sets VAR_DUMPER_FORMAT=html, which turns VarDumper::setHandler() into a
        // no-op (its guard against overriding an operator-fixed format). Clearing it lets the
        // capturing handler install, so dump() feeds the card list instead of writing to stdout.
        unset($_SERVER['VAR_DUMPER_FORMAT']);

        VarDumper::setHandler(function (mixed $value, ?string $label = null) use ($emit): void {
            $emit(new DumpFeedItem($this->renderer->render($value, $label)));
        });
    }
}
