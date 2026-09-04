<?php

declare(strict_types=1);

namespace App\Support\FeedItems;

use App\Enums\FeedItemKind;
use App\Support\Duration;

class QueryFeedItem extends FeedItem
{
    private const int SLOW_THRESHOLD_MS = 100;

    /** Set by SnippetRunRecorder when an identical statement already ran in the same snippet run. */
    public bool $duplicate = false;

    public function __construct(
        public string $sql,
        public float $durationMs,
        public string $connection,
    ) {}

    public function toArray(): array
    {
        return [
            'kind' => FeedItemKind::Query->value,
            'sql' => $this->sql,
            'duration_str' => Duration::format($this->durationMs),
            'duration_ms' => $this->durationMs,
            'connection' => $this->connection,
            'slow' => $this->durationMs >= self::SLOW_THRESHOLD_MS,
            'duplicate' => $this->duplicate,
            'line' => $this->line,
        ];
    }
}
