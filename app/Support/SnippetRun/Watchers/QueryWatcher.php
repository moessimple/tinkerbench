<?php

declare(strict_types=1);

namespace App\Support\SnippetRun\Watchers;

use App\Support\SnippetRun\FeedItems\QueryFeedItem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;

class QueryWatcher implements Watcher
{
    public function register(Application $app, callable $emit): void
    {
        $app->make(DatabaseManager::class)->connection()->listen(function (QueryExecuted $query) use ($emit): void {
            $emit(new QueryFeedItem($query->toRawSql(), $query->time, $query->connectionName));
        });
    }
}
