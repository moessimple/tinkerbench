<?php

declare(strict_types=1);

use Tinkerbench\Runner\Watchers\DumpWatcher;
use Tinkerbench\Runner\Watchers\LogWatcher;
use Tinkerbench\Runner\Watchers\QueryWatcher;
use Tinkerbench\Runner\Watchers\Watcher;

it('is implemented by every watcher SnippetRunRecorder collects', function (): void {
    expect(DumpWatcher::class)->toImplement(Watcher::class)
        ->and(QueryWatcher::class)->toImplement(Watcher::class)
        ->and(LogWatcher::class)->toImplement(Watcher::class);
});
