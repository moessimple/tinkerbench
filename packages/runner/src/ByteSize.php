<?php

declare(strict_types=1);

namespace Tinkerbench\Runner;

class ByteSize
{
    /**
     * Reproduces Illuminate\Support\Number::fileSize($bytes, precision: 2) for the "en" locale
     * (unit step at 0.9 of the next unit, two decimals, "." decimal separator) so the basic
     * pipeline needs no illuminate/support. The Laravel pipeline formats peak memory through the
     * same call, so this output must stay byte-identical to Number::fileSize. The 0.9 step keeps
     * every displayed value under 1000, so thousands grouping never applies.
     *
     * @var list<string>
     */
    private const UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

    public static function format(int $bytes): string
    {
        $value = (float) $bytes;

        for ($unit = 0; $value / 1024 > 0.9 && $unit < count(self::UNITS) - 1; $unit++) {
            $value /= 1024;
        }

        return number_format($value, 2, '.', ',').' '.self::UNITS[$unit];
    }
}
