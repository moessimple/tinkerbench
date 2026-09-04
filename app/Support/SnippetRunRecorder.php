<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Watchers\Watcher;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Number;
use Throwable;

class SnippetRunRecorder
{
    /** @var list<array<string, mixed>> */
    private array $items = [];

    /** @var array<string, true> */
    private array $seenQueries = [];

    /**
     * "Model::relation" => index of that finding's item in $items. A repeat access folds into the
     * first item's count instead of appending, so one N+1 shows as one card.
     *
     * @var array<string, int>
     */
    private array $nPlusOneIndex = [];

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
        $this->items[] = ['kind' => 'result', 'html' => $html];
    }

    /**
     * @return array{items: list<array<string, mixed>>, duration_str: string, peak_memory_str: string}
     */
    public function snapshot(): array
    {
        return [
            'items' => $this->itemsWithoutSingleLazyLoads(),
            'duration_str' => Duration::format($this->elapsedMilliseconds()),
            'peak_memory_str' => Number::fileSize(memory_get_peak_usage(true), precision: 2),
        ];
    }

    /**
     * A relation lazy-loaded exactly once is a single extra query, not an N+1. The folded finding
     * is only reported once the same relation has been lazy-loaded at least twice in the run.
     *
     * @return list<array<string, mixed>>
     */
    private function itemsWithoutSingleLazyLoads(): array
    {
        return array_values(array_filter($this->items, function (array $item): bool {
            if (($item['kind'] ?? null) !== 'n_plus_one') {
                return true;
            }

            $count = $item['count'] ?? 0;

            return is_int($count) && $count >= 2;
        }));
    }

    /**
     * A query counts as a duplicate only when the identical statement and bindings ran before,
     * matching Laravel Debugbar's rule (bindings are already inlined into `sql`). The same
     * statement with different bindings is an N+1, which this flag deliberately does not cover.
     *
     * @param  array<string, mixed>  $item
     */
    private function append(array $item): void
    {
        if (($item['kind'] ?? null) === 'query' && is_string($item['sql'] ?? null)) {
            if (isset($this->seenQueries[$item['sql']])) {
                $item['duplicate'] = true;
            }

            $this->seenQueries[$item['sql']] = true;
        }

        if (($item['kind'] ?? null) === 'n_plus_one' && is_string($item['model'] ?? null) && is_string($item['relation'] ?? null)) {
            $key = $item['model'].'::'.$item['relation'];

            if (isset($this->nPlusOneIndex[$key])) {
                $existing = $this->items[$this->nPlusOneIndex[$key]];
                $count = $existing['count'] ?? 0;
                $existing['count'] = (is_int($count) ? $count : 0) + 1;
                $this->items[$this->nPlusOneIndex[$key]] = $existing;

                return;
            }

            $item['count'] = 1;
            $this->nPlusOneIndex[$key] = count($this->items);
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
