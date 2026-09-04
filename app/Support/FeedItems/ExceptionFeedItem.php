<?php

declare(strict_types=1);

namespace App\Support\FeedItems;

use App\Enums\FeedItemKind;

class ExceptionFeedItem extends FeedItem
{
    /**
     * @param  list<array{file: string, line: int, function: string|null, vendor: bool, snippet: bool}>  $frames
     */
    public function __construct(
        public string $type,
        public string $message,
        public array $frames,
    ) {}

    public function toArray(): array
    {
        return [
            'kind' => FeedItemKind::Exception->value,
            'type' => $this->type,
            'message' => $this->message,
            'line' => $this->line,
            'frames' => $this->frames,
        ];
    }
}
