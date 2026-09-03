<?php

declare(strict_types=1);

namespace App\Support;

class Duration
{
    private const float MILLISECONDS_PER_SECOND = 1000;

    public static function format(float $milliseconds): string
    {
        if ($milliseconds >= self::MILLISECONDS_PER_SECOND) {
            return sprintf('%.2fs', $milliseconds / self::MILLISECONDS_PER_SECOND);
        }

        return sprintf('%.2fms', $milliseconds);
    }
}
