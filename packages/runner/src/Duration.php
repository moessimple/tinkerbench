<?php

declare(strict_types=1);

namespace Tinkerbench\Runner;

class Duration
{
    /** Native class-constant types need PHP 8.3+; this package's floor is 8.2. */
    private const MILLISECONDS_PER_SECOND = 1000;

    public static function format(float $milliseconds): string
    {
        if ($milliseconds >= self::MILLISECONDS_PER_SECOND) {
            return sprintf('%.2fs', $milliseconds / self::MILLISECONDS_PER_SECOND);
        }

        return sprintf('%.2fms', $milliseconds);
    }
}
