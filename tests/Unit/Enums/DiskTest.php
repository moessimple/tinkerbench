<?php

declare(strict_types=1);

use App\Enums\Disk;

it('has exactly the expected disks', function (): void {
    expect(Disk::cases())->toBe([
        Disk::Snippets,
    ]);
});

it('backs the snippets disk with its config key', function (): void {
    expect(Disk::Snippets->value)->toBe('snippets');
});
