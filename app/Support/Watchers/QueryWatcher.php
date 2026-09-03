<?php

declare(strict_types=1);

namespace App\Support\Watchers;

use App\Support\Duration;
use App\Support\SourceLocator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;

class QueryWatcher implements Watcher
{
    private const int SLOW_QUERY_THRESHOLD_MS = 100;

    public function __construct(private SourceLocator $source) {}

    /**
     * @param  callable(array{kind: 'query', sql: string, duration_str: string, duration_ms: float, connection: string, slow: bool, duplicate: bool, line: int|null}): void  $emit
     */
    public function register(Application $app, callable $emit): void
    {
        $app->make(DatabaseManager::class)->connection()->listen(function (QueryExecuted $query) use ($emit): void {
            $emit([
                'kind' => 'query',
                'sql' => $query->toRawSql(),
                'duration_str' => Duration::format($query->time),
                'duration_ms' => $query->time,
                'connection' => $query->connectionName,
                'slow' => $query->time >= self::SLOW_QUERY_THRESHOLD_MS,
                'duplicate' => false,
                'line' => $this->source->snippetLine(),
            ]);
        });
    }
}
