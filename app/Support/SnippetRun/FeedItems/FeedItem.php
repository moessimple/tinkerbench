<?php

declare(strict_types=1);

namespace App\Support\SnippetRun\FeedItems;

/**
 * One entry in a snippet run's output feed. A watcher builds a subclass and emits it; SnippetRunRecorder
 * stamps {@see self::$line} and calls toArray() to serialize it for the frontend's FeedItem union.
 */
abstract class FeedItem
{
    /**
     * Snippet line that produced this entry, or null: either no snippet frame was on the stack, or the
     * item never went through SnippetRunRecorder::append() (ExceptionFeedItem sets its own line;
     * ResultFeedItem has none).
     */
    public ?int $line = null;

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
