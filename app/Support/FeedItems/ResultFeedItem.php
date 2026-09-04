<?php

declare(strict_types=1);

namespace App\Support\FeedItems;

use App\Enums\FeedItemKind;

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
