<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Watchers\DumpWatcher;
use App\Support\Watchers\LogWatcher;
use App\Support\Watchers\QueryWatcher;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

class SnippetRunRecorder
{
    /** @var list<array<string, mixed>> */
    private array $items = [];

    /** @var array<string, true> */
    private array $seenQueries = [];

    private ?float $startedAt = null;

    private ?float $finishedAt = null;

    public function __construct(
        private DumpWatcher $dumpWatcher,
        private QueryWatcher $queryWatcher,
        private LogWatcher $logWatcher,
        private ExceptionMapper $exceptionMapper,
    ) {}

    public function record(Application $app, Closure $run): void
    {
        $emit = $this->append(...);

        $this->dumpWatcher->register($app, $emit);
        $this->queryWatcher->register($app, $emit);
        $this->logWatcher->register($app, $emit);

        $this->startedAt = $this->now();

        try {
            $run();
        } finally {
            $this->finishedAt = $this->now();
        }
    }

    public function appendException(Throwable $throwable, ?int $line): void
    {
        $this->items[] = $this->exceptionMapper->toItem($throwable, $line);
    }

    /**
     * @return array{items: list<array<string, mixed>>, duration_str: string, peak_memory_str: string}
     */
    public function snapshot(): array
    {
        return [
            'items' => $this->items,
            'duration_str' => Duration::format($this->elapsedMilliseconds()),
            'peak_memory_str' => $this->formatMemory(memory_get_peak_usage(true)),
        ];
    }

    /**
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

    private function formatMemory(int $bytes): string
    {
        return sprintf('%.2fMB', $bytes / 1024 / 1024);
    }
}
