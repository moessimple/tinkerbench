<?php

declare(strict_types=1);

namespace App\Support\SnippetRun\FeedItems;

use App\Enums\FeedItemKind;
use App\Support\SnippetRun\ValueRenderer;

class LogFeedItem extends FeedItem
{
    /**
     * @param  array<array-key, mixed>  $context  Monolog context, rendered to a VarDumper tree for the feed.
     */
    public function __construct(
        public string $label,
        public string $message,
        public array $context,
        private ValueRenderer $renderer = new ValueRenderer(),
    ) {}

    public function toArray(): array
    {
        $hasContext = $this->context !== [];

        return [
            'kind' => FeedItemKind::Log->value,
            'label' => $this->label,
            'message' => $this->message,
            'context_html' => $hasContext ? $this->renderer->render($this->context) : null,
            'context_text' => $hasContext ? $this->renderer->renderText($this->context) : null,
            'line' => $this->line,
        ];
    }
}
