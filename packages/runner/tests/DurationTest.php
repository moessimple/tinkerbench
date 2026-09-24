<?php

declare(strict_types=1);

use Tinkerbench\Runner\Duration;

it('formats a sub-second duration in milliseconds', function (): void {
    expect(Duration::format(12.3))->toBe('12.30 ms');
});

it('formats a duration of a second or more in seconds', function (): void {
    expect(Duration::format(2500))->toBe('2.50 s');
});

it('formats exactly one second in seconds', function (): void {
    expect(Duration::format(1000))->toBe('1.00 s');
});

it('formats a duration below a millisecond in whole microseconds', function (): void {
    expect(Duration::format(0.6))->toBe('600 μs')
        ->and(Duration::format(0.0))->toBe('0 μs');
});

it('switches to milliseconds when the microseconds round up to a millisecond', function (): void {
    expect(Duration::format(0.9994))->toBe('999 μs')
        ->and(Duration::format(0.9996))->toBe('1.00 ms');
});

it('switches to seconds when the milliseconds round up to a second', function (): void {
    expect(Duration::format(999.994))->toBe('999.99 ms')
        ->and(Duration::format(999.996))->toBe('1.00 s');
});

it('keeps the unit of the magnitude for a negative duration', function (): void {
    expect(Duration::format(-2.0))->toBe('-2.00 ms')
        ->and(Duration::format(-0.5))->toBe('-500 μs');
});
