<?php

declare(strict_types=1);

namespace Tinkerbench\Runner\FeedItems;

use Tinkerbench\Runner\Duration;
use Tinkerbench\Runner\FeedItemKind;

class QueryFeedItem extends FeedItem
{
    /** Native class-constant types need PHP 8.3+; this package's floor is 8.2. */
    private const SLOW_THRESHOLD_MS = 100;

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
