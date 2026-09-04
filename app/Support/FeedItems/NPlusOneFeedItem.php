<?php

declare(strict_types=1);

namespace App\Support\FeedItems;

use App\Enums\FeedItemKind;

class NPlusOneFeedItem extends FeedItem
{
    /** Lazy loads of this relation in the run; incremented by SnippetRunRecorder as repeats fold in. */
    public int $count = 1;

    public function __construct(
        public string $model,
        public string $relation,
    ) {}

    public function toArray(): array
    {
        return [
            'kind' => FeedItemKind::NPlusOne->value,
            'model' => $this->model,
            'relation' => $this->relation,
            'count' => $this->count,
            'line' => $this->line,
        ];
    }
}
