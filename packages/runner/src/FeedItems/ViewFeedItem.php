<?php

declare(strict_types=1);

namespace Tinkerbench\Runner\FeedItems;

use Tinkerbench\Runner\FeedItemKind;

class ViewFeedItem extends FeedItem
{
    /**
     * @param  string  $path  View file path, as returned by Illuminate\View\View::getPath().
     * @param  string  $dataHtml  Interactive VarDumper HTML of the view's data, for display.
     * @param  string  $dataText  Plain-text form of the same data, for the copy-to-clipboard button.
     */
    public function __construct(
        public string $path,
        public string $dataHtml,
        public string $dataText,
    ) {}

    public function toArray(): array
    {
        return [
            'kind' => FeedItemKind::View->value,
            'path' => $this->path,
            'data_html' => $this->dataHtml,
            'data_text' => $this->dataText,
            'line' => $this->line,
        ];
    }
}
