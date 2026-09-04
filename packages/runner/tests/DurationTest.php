<?php

declare(strict_types=1);

use Tinkerbench\Runner\Duration;

it('formats a sub-second duration in milliseconds', function (): void {
    expect(Duration::format(12.3))->toBe('12.30ms');
});

it('formats a duration of a second or more in seconds', function (): void {
    expect(Duration::format(2500))->toBe('2.50s');
});

it('formats exactly one second in seconds', function (): void {
    expect(Duration::format(1000))->toBe('1.00s');
});
