<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\Backtrace\Backtrace;
use Spatie\Backtrace\Frame;
use Throwable;

class ExceptionMapper
{
    /** @var list<class-string> */
    private const array HARNESS_CLASSES = [SnippetRunner::class, SnippetRunRecorder::class];

    /**
     * @param  string  $applicationPath  Target project root; frames outside it (or under its
     *                                   vendor/) are marked as vendor frames.
     * @param  string  $snippetPath  realpath()-resolved snippet file, so its own frame can be
     *                               shown as "snippet" instead of a throwaway temp path.
     */
    public function __construct(
        private string $applicationPath,
        private string $snippetPath,
    ) {}

    /**
     * @param  bool  $includeFrames  Pass false for a synthesized fatal (memory exhaustion, timeout):
     *                               its backtrace points at the runner internals, not the snippet.
     * @return array{kind: 'exception', type: string, message: string, line: int|null, frames: list<array{file: string, line: int, function: string|null, vendor: bool, snippet: bool}>}
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
     * @return list<array{file: string, line: int, function: string|null, vendor: bool, snippet: bool}>
     */
    private function frames(Throwable $throwable): array
    {
        $frames = Backtrace::createForThrowable($throwable)
            ->applicationPath($this->applicationPath)
            ->frames();

        return array_map($this->mapFrame(...), $this->stopAtHarness(array_values($frames)));
    }

    /**
     * Everything from the first App\Support\SnippetRunner / SnippetRunRecorder frame downward is
     * the harness that booted and required the snippet, never the user's own stack.
     *
     * @param  list<Frame>  $frames
     * @return list<Frame>
     */
    private function stopAtHarness(array $frames): array
    {
        foreach ($frames as $index => $frame) {
            if (in_array($frame->class, self::HARNESS_CLASSES, true)) {
                return array_slice($frames, 0, $index);
            }
        }

        return $frames;
    }

    /**
     * @return array{file: string, line: int, function: string|null, vendor: bool, snippet: bool}
     */
    private function mapFrame(Frame $frame): array
    {
        return [
            'file' => (string) $frame->file,
            'line' => (int) $frame->lineNumber,
            'function' => $this->formatFunction($frame),
            'vendor' => ! $frame->applicationFrame,
            'snippet' => $frame->file === $this->snippetPath,
        ];
    }

    private function formatFunction(Frame $frame): ?string
    {
        if ($frame->class !== null && $frame->method !== null) {
            return $frame->class.'::'.$frame->method;
        }

        return $frame->method;
    }
}
