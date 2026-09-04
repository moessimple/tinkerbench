<?php

declare(strict_types=1);

namespace App\Support\SnippetRun\FeedItems;

use App\Enums\FeedItemKind;

class DumpFeedItem extends FeedItem
{
    public function __construct(public string $html) {}

    public function toArray(): array
    {
        return [
            'kind' => FeedItemKind::Dump->value,
            'html' => $this->html,
            'line' => $this->line,
        ];
    }
}
