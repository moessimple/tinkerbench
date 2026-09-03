<?php

declare(strict_types=1);

use App\Support\Watchers\DumpWatcher;
use App\Support\Watchers\LogWatcher;
use App\Support\Watchers\QueryWatcher;
use App\Support\Watchers\Watcher;

it('is implemented by every watcher SnippetRunRecorder collects', function (): void {
    expect(DumpWatcher::class)->toImplement(Watcher::class)
        ->and(QueryWatcher::class)->toImplement(Watcher::class)
        ->and(LogWatcher::class)->toImplement(Watcher::class);
});
