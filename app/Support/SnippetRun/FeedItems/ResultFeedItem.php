<?php

declare(strict_types=1);

namespace App\Support\SnippetRun\FeedItems;

use App\Enums\FeedItemKind;

/**
 * The snippet's own return value. SnippetRunRecorder::appendResult() adds it directly instead of
 * through append(), so the inherited $line is never stamped and toArray() omits it.
 */
class ResultFeedItem extends FeedItem
{
    public function __construct(public string $html) {}

    public function toArray(): array
    {
        return [
            'kind' => FeedItemKind::Result->value,
            'html' => $this->html,
        ];
    }
}
