<?php

declare(strict_types=1);

namespace Tinkerbench\Runner;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Throwable;
use Tinkerbench\Runner\FeedItems\DumpFeedItem;
use Tinkerbench\Runner\FeedItems\FeedItem;
use Tinkerbench\Runner\FeedItems\HttpClientFeedItem;
use Tinkerbench\Runner\FeedItems\NPlusOneFeedItem;
use Tinkerbench\Runner\FeedItems\QueryFeedItem;
use Tinkerbench\Runner\FeedItems\ResultFeedItem;
use Tinkerbench\Runner\Watchers\Watcher;

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
     * @param  float  $runStartedAt  hrtime(true) taken when the run began, before the target was
     *                               booted. Boot time is measured from here to the snippet start,
     *                               on the same clock, so boot and snippet time leave no gap.
     */
    public function __construct(
        private readonly array $watchers,
        private readonly ExceptionMapper $exceptionMapper,
        private readonly SourceLocator $source,
        private readonly float $runStartedAt,
    ) {}

    /**
     * $app is null for the basic (non-Laravel) pipeline: it registers no watchers, since every
     * Watcher::register() needs an Application. Dump capture is installed by the caller instead.
     */
    public function record(?Application $app, Closure $run): void
    {
        $emit = $this->append(...);

        if ($app instanceof Application) {
            foreach ($this->watchers as $watcher) {
                $watcher->register($app, $emit);
            }
        }

        $this->startedAt = $this->now();

        try {
            $run();
        } finally {
            $this->finishedAt = $this->now();
        }
    }

    /**
     * Records a dump for the basic (non-Laravel) pipeline, which captures dumps without a
     * DumpWatcher. $html/$text are already rendered by the caller; the snippet line is stamped
     * here, exactly as it is for a watcher-emitted item.
     */
    public function appendDump(string $html, string $text): void
    {
        $this->append(new DumpFeedItem($html, $text));
    }

    public function appendException(Throwable $throwable, ?int $line, bool $includeFrames = true): void
    {
        $this->items[] = $this->exceptionMapper->toItem($throwable, $line, $includeFrames);
    }

    /**
     * Records the snippet's own return value, already rendered by the caller: $html is the
     * interactive VarDumper dump, $text its plain-text form for the copy button.
     */
    public function appendResult(string $html, string $text): void
    {
        $this->items[] = new ResultFeedItem($html, $text);
    }

    /**
     * The snippet duration is split into query, http, and php time so the numbers add up: each
     * part is rounded to hundredths first and php is the remainder of the rounded values, so
     * duration = query + http + php holds exactly for the displayed figures. Php time is
     * everything in the PHP process outside the database driver and HTTP calls, including class
     * loading. Query time is the plain sum of the query items, which keeps it checkable against
     * the cards. Http time is the time at least one request was in flight: parallel requests
     * (Http::pool(), async) overlap, and summing them would count the same wall time twice. For
     * sequential requests it equals the sum of the cards.
     *
     * Boot time runs from the run start to the snippet start; run = boot + duration holds the same
     * way, from the rounded values. The snippet duration itself is not part of the snapshot,
     * since the four parts already show it.
     *
     * @return array{
     *     items: list<array<string, mixed>>,
     *     boot_duration_str: string,
     *     boot_duration_ms: float,
     *     run_duration_str: string,
     *     run_duration_ms: float,
     *     peak_memory_str: string,
     *     query_count: int,
     *     duplicate_query_count: int,
     *     query_duration_str: string,
     *     query_duration_ms: float,
     *     http_request_count: int,
     *     http_duration_str: string,
     *     http_duration_ms: float,
     *     php_duration_str: string,
     *     php_duration_ms: float,
     * }
     */
    public function snapshot(): array
    {
        $queries = array_values(array_filter($this->items, static fn (FeedItem $item): bool => $item instanceof QueryFeedItem));
        $httpCalls = array_values(array_filter($this->items, static fn (FeedItem $item): bool => $item instanceof HttpClientFeedItem));

        $durationMs = round($this->elapsedMilliseconds(), 2);
        $bootDurationMs = round($this->bootMilliseconds(), 2);
        $runDurationMs = round($bootDurationMs + $durationMs, 2);
        $queryDurationMs = round(array_sum(array_map(static fn (QueryFeedItem $query): float => $query->durationMs, $queries)), 2);
        $httpDurationMs = round($this->inFlightMilliseconds($httpCalls), 2);
        $phpDurationMs = round($durationMs - $queryDurationMs - $httpDurationMs, 2);

        return [
            'items' => array_map(
                static fn (FeedItem $item): array => $item->toArray(),
                $this->itemsWithoutSingleLazyLoads(),
            ),
            'boot_duration_str' => Duration::format($bootDurationMs),
            'boot_duration_ms' => $bootDurationMs,
            'run_duration_str' => Duration::format($runDurationMs),
            'run_duration_ms' => $runDurationMs,
            'peak_memory_str' => ByteSize::format(memory_get_peak_usage(true)),
            'query_count' => count($queries),
            'duplicate_query_count' => count(array_filter($queries, static fn (QueryFeedItem $query): bool => $query->duplicate)),
            'query_duration_str' => Duration::format($queryDurationMs),
            'query_duration_ms' => $queryDurationMs,
            'http_request_count' => count($httpCalls),
            'http_duration_str' => Duration::format($httpDurationMs),
            'http_duration_ms' => $httpDurationMs,
            'php_duration_str' => Duration::format($phpDurationMs),
            'php_duration_ms' => $phpDurationMs,
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

    /**
     * The length of the union of the calls' time spans, so overlapping calls count once.
     *
     * @param  list<HttpClientFeedItem>  $calls
     */
    private function inFlightMilliseconds(array $calls): float
    {
        usort($calls, static fn (HttpClientFeedItem $a, HttpClientFeedItem $b): int => $a->startedAt <=> $b->startedAt);

        $inFlight = 0.0;
        $coveredUntil = -INF;

        foreach ($calls as $call) {
            $endedAt = $call->startedAt + $call->durationMs * 1_000_000;

            if ($endedAt <= $coveredUntil) {
                continue;
            }

            $inFlight += $endedAt - max($call->startedAt, $coveredUntil);
            $coveredUntil = $endedAt;
        }

        return $inFlight / 1_000_000;
    }

    private function bootMilliseconds(): float
    {
        if ($this->startedAt === null) {
            return 0.0;
        }

        return ($this->startedAt - $this->runStartedAt) / 1_000_000;
    }

    private function now(): float
    {
        return (float) hrtime(true);
    }
}
