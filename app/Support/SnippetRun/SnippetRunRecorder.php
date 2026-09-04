<?php

declare(strict_types=1);

namespace App\Support\SnippetRun;

use App\Support\SnippetRun\FeedItems\FeedItem;
use App\Support\SnippetRun\FeedItems\NPlusOneFeedItem;
use App\Support\SnippetRun\FeedItems\QueryFeedItem;
use App\Support\SnippetRun\FeedItems\ResultFeedItem;
use App\Support\SnippetRun\Watchers\Watcher;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Number;
use Throwable;

class SnippetRunRecorder
{
    /** @var list<FeedItem> */
    private array $items = [];

    /** @var array<string, true> */
    private array $seenQueries = [];

    /**
     * "Model::relation" => the folded item for that finding. A repeat lazy load increments its
     * count instead of appending, so one N+1 shows as one card.
     *
     * @var array<string, NPlusOneFeedItem>
     */
    private array $foldedNPlusOne = [];

    private ?float $startedAt = null;

    private ?float $finishedAt = null;

    /**
     * @param  list<Watcher>  $watchers  Every feed-item source for the run. ExceptionMapper is not
     *                                   one of these: it turns caught throwables and fatal shutdown
     *                                   errors into items, it does not listen to an event.
     */
    public function __construct(
        private array $watchers,
        private ExceptionMapper $exceptionMapper,
        private SourceLocator $source,
    ) {}

    public function record(Application $app, Closure $run): void
    {
        $emit = $this->append(...);

        foreach ($this->watchers as $watcher) {
            $watcher->register($app, $emit);
        }

        $this->startedAt = $this->now();

        try {
            $run();
        } finally {
            $this->finishedAt = $this->now();
        }
    }

    public function appendException(Throwable $throwable, ?int $line, bool $includeFrames = true): void
    {
        $this->items[] = $this->exceptionMapper->toItem($throwable, $line, $includeFrames);
    }

    /**
     * Records the snippet's own return value, already rendered to VarDumper HTML by the caller.
     */
    public function appendResult(string $html): void
    {
        $this->items[] = new ResultFeedItem($html);
    }

    /**
     * @return array{items: list<array<string, mixed>>, duration_str: string, peak_memory_str: string}
     */
    public function snapshot(): array
    {
        return [
            'items' => array_map(
                static fn (FeedItem $item): array => $item->toArray(),
                $this->itemsWithoutSingleLazyLoads(),
            ),
            'duration_str' => Duration::format($this->elapsedMilliseconds()),
            'peak_memory_str' => Number::fileSize(memory_get_peak_usage(true), precision: 2),
        ];
    }

    /**
     * A relation lazy-loaded exactly once is a single extra query, not an N+1. The folded finding
     * is only reported once the same relation has been lazy-loaded at least twice in the run.
     *
     * @return list<FeedItem>
     */
    private function itemsWithoutSingleLazyLoads(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (FeedItem $item): bool => ! $item instanceof NPlusOneFeedItem || $item->count >= 2,
        ));
    }

    /**
     * Stamps the snippet line on the item, then folds it into the run: an identical repeated query
     * is flagged as a duplicate (bindings are inlined into the SQL, so the same statement with
     * different bindings is an N+1 this flag deliberately ignores), and a repeated lazy load of the
     * same relation increments the first finding's count instead of appending a second card.
     */
    private function append(FeedItem $item): void
    {
        $item->line = $this->source->snippetLine();

        if ($item instanceof QueryFeedItem) {
            $item->duplicate = isset($this->seenQueries[$item->sql]);
            $this->seenQueries[$item->sql] = true;
        }

        if ($item instanceof NPlusOneFeedItem) {
            $key = $item->model.'::'.$item->relation;

            if (isset($this->foldedNPlusOne[$key])) {
                $this->foldedNPlusOne[$key]->count++;

                return;
            }

            $this->foldedNPlusOne[$key] = $item;
        }

        $this->items[] = $item;
    }

    private function elapsedMilliseconds(): float
    {
        if ($this->startedAt === null) {
            return 0.0;
        }

        return (($this->finishedAt ?? $this->now()) - $this->startedAt) / 1_000_000;
    }

    private function now(): float
    {
        return (float) hrtime(true);
    }
}
