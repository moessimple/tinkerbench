<?php

declare(strict_types=1);

use Support\Herd;

test('resolves the php binary from the configured herd bin path', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);

    expect(new Herd()->phpBinary())->toBe('/tmp/herd-bin/php');
});

test('throws when the herd bin path is not configured', function (): void {
    config(['services.herd.bin' => '']);

    new Herd()->phpBinary();
})->throws(InvalidArgumentException::class);
