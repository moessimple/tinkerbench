<?php

declare(strict_types=1);

namespace Tinkerbench\Runner\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;
use Tinkerbench\Runner\FeedItems\QueryFeedItem;

class QueryWatcher implements Watcher
{
    public function register(Application $app, callable $emit): void
    {
        $app->make(DatabaseManager::class)->connection()->listen(function (QueryExecuted $query) use ($emit): void {
            $emit(new QueryFeedItem($query->toRawSql(), $query->time, $query->connectionName));
        });
    }
}
