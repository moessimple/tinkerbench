<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame;
use Throwable;

class ExceptionMapper
{
    private const int SNIPPET_LINE_COUNT = 12;

    /**
     * @param  string  $applicationPath  Target project root; frames outside it (or under its
     *                                   vendor/) are marked as vendor frames.
     */
    public function __construct(private string $applicationPath) {}

    /**
     * @param  bool  $includeFrames  Pass false for a synthesized fatal (memory exhaustion, timeout):
     *                               its backtrace points at the runner internals, not the snippet.
     * @return array{kind: 'exception', type: string, message: string, line: int|null, frames: list<array{file: string, line: int, function: string|null, vendor: bool, snippet?: list<array{line: int, code: string}>}>}
     */
    public function toItem(Throwable $throwable, ?int $line, bool $includeFrames = true): array
    {
        return [
            'kind' => 'exception',
            'type' => $throwable::class,
            'message' => $throwable->getMessage(),
            'line' => $line,
            'frames' => $includeFrames ? $this->frames($throwable) : [],
        ];
    }

    /**
     * @return list<array{file: string, line: int, function: string|null, vendor: bool, snippet?: list<array{line: int, code: string}>}>
     */
    private function frames(Throwable $throwable): array
    {
        $frames = Backtrace::createForThrowable($throwable)
            ->applicationPath($this->applicationPath)
            ->frames();

        return array_values(array_map($this->mapFrame(...), $frames));
    }

    /**
     * @return array{file: string, line: int, function: string|null, vendor: bool, snippet?: list<array{line: int, code: string}>}
     */
    private function mapFrame(Frame $frame): array
    {
        $mapped = [
            'file' => (string) $frame->file,
            'line' => (int) $frame->lineNumber,
            'function' => $this->formatFunction($frame),
            'vendor' => ! $frame->applicationFrame,
        ];

        if (! $frame->applicationFrame) {
            return $mapped;
        }

        $snippet = $this->normalizeSnippet($frame->getSnippet(self::SNIPPET_LINE_COUNT));

        if ($snippet !== []) {
            $mapped['snippet'] = $snippet;
        }

        return $mapped;
    }

    /**
     * @param  array<int|string, mixed>  $snippet  spatie/backtrace returns line number => source text.
     * @return list<array{line: int, code: string}>
     */
    private function normalizeSnippet(array $snippet): array
    {
        $lines = [];

        foreach ($snippet as $number => $code) {
            $lines[] = [
                'line' => (int) $number,
                'code' => is_string($code) ? $code : '',
            ];
        }

        return $lines;
    }

    private function formatFunction(Frame $frame): ?string
    {
        if ($frame->class !== null && $frame->method !== null) {
            return $frame->class.'::'.$frame->method;
        }

        return $frame->method;
    }
}
