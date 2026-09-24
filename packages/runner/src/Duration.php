<?php

declare(strict_types=1);

namespace Tinkerbench\Runner;

class Duration
{
    /** Native class-constant types need PHP 8.3+; this package's floor is 8.2. */
    private const MICROSECONDS_PER_MILLISECOND = 1000;

    private const MILLISECONDS_PER_SECOND = 1000;

    /**
     * Whole μs below 1 ms, ms below 1 s, s above. The unit follows the rounded magnitude, so
     * 0.9996 ms reads "1.00 ms", not "1000 μs", and a negative time keeps its unit.
     */
    public static function format(float $milliseconds): string
    {
        $microseconds = round($milliseconds * self::MICROSECONDS_PER_MILLISECOND);

        if (abs($microseconds) < self::MICROSECONDS_PER_MILLISECOND) {
            return sprintf('%d μs', $microseconds);
        }

        if (abs(round($milliseconds, 2)) < self::MILLISECONDS_PER_SECOND) {
            return sprintf('%.2f ms', $milliseconds);
        }

        return sprintf('%.2f s', $milliseconds / self::MILLISECONDS_PER_SECOND);
    }
}
