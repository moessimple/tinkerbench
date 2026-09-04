<?php

declare(strict_types=1);

namespace Tinkerbench\Runner\FeedItems;

use Tinkerbench\Runner\FeedItemKind;

class DumpFeedItem extends FeedItem
{
    /**
     * @param  string  $html  Interactive VarDumper HTML for display.
     * @param  string  $text  Plain-text form of the same value, for the copy-to-clipboard button.
     */
    public function __construct(
        public string $html,
        public string $text,
    ) {}

    public function toArray(): array
    {
        return [
            'kind' => FeedItemKind::Dump->value,
            'html' => $this->html,
            'text' => $this->text,
            'line' => $this->line,
        ];
    }
}
