<?php

declare(strict_types=1);

use Tinkerbench\Runner\ByteSize;

it('formats a byte count with a unit and two decimals, matching Number::fileSize', function (int $bytes, string $expected): void {
    expect(ByteSize::format($bytes))->toBe($expected);
})->with([
    'zero' => [0, '0.00 B'],
    'below the step threshold' => [512, '512.00 B'],
    'one kibibyte' => [1024, '1.00 KB'],
    'steps up just past 0.9 KiB' => [922, '0.90 KB'],
    'one mebibyte' => [1024 * 1024, '1.00 MB'],
    'a typical peak-memory value' => [22 * 1024 * 1024, '22.00 MB'],
    'one gibibyte' => [1024 ** 3, '1.00 GB'],
    'two tebibytes' => [2 * 1024 ** 4, '2.00 TB'],
]);
