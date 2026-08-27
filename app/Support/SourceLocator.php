<?php

declare(strict_types=1);

namespace App\Support;

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

    public function snippetLine(): ?int
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (($frame['file'] ?? null) === $this->snippetPath) {
                return $frame['line'] ?? null;
            }
        }

        return null;
    }
}
