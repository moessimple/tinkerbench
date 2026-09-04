<?php

declare(strict_types=1);

namespace App\Support\SnippetRun\FeedItems;

use App\Enums\FeedItemKind;

class LogFeedItem extends FeedItem
{
    /**
     * @param  string|null  $contextHtml  Monolog context as an interactive VarDumper tree, null when the context is empty.
     * @param  string|null  $contextText  Plain-text form of the same context, for the copy-to-clipboard button.
     */
    public function __construct(
        public string $label,
        public string $message,
        public ?string $contextHtml,
        public ?string $contextText,
    ) {}

    public function toArray(): array
    {
        return [
            'kind' => FeedItemKind::Log->value,
            'label' => $this->label,
            'message' => $this->message,
            'context_html' => $this->contextHtml,
            'context_text' => $this->contextText,
            'line' => $this->line,
        ];
    }
}
