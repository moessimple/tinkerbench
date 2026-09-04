<?php

declare(strict_types=1);

namespace App\Support\SnippetRun\FeedItems;

use App\Enums\FeedItemKind;

class LogFeedItem extends FeedItem
{
    /**
     * @param  array<array-key, mixed>  $context  Monolog context, JSON-encoded for the feed.
     */
    public function __construct(
        public string $label,
        public string $message,
        public array $context,
    ) {}

    public function toArray(): array
    {
        return [
            'kind' => FeedItemKind::Log->value,
            'label' => $this->label,
            'message' => $this->message,
            'context' => $this->encodedContext(),
            'line' => $this->line,
        ];
    }

    private function encodedContext(): ?string
    {
        if ($this->context === []) {
            return null;
        }

        $encoded = json_encode($this->context);

        return $encoded === false ? null : $encoded;
    }
}
