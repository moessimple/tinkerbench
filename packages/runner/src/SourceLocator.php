<?php

declare(strict_types=1);

namespace Tinkerbench\Runner;

use Throwable;

class SourceLocator
{
    private readonly string $snippetPath;

    /**
     * $snippetPath is realpath()-resolved on construction: PHP's backtrace frames always report a
     * resolved path, so comparing an unresolved one would never match and every captured item's
     * line would come back null.
     */
    public function __construct(string $snippetPath)
    {
        $this->snippetPath = realpath($snippetPath) ?: $snippetPath;
    }

    public function path(): string
    {
        return $this->snippetPath;
    }

    public function snippetLine(): ?int
    {
        return $this->lineIn(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
    }

    public function throwableLine(Throwable $throwable): ?int
    {
        if ($throwable->getFile() === $this->snippetPath) {
            return $throwable->getLine();
        }

        return $this->lineIn($throwable->getTrace());
    }

    /**
     * @param  iterable<array<string, mixed>>  $frames
     */
    private function lineIn(iterable $frames): ?int
    {
        foreach ($frames as $frame) {
            if (($frame['file'] ?? null) !== $this->snippetPath) {
                continue;
            }

            $line = $frame['line'] ?? null;

            return is_int($line) ? $line : null;
        }

        return null;
    }
}
