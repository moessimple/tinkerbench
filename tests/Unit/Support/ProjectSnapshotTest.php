<?php

declare(strict_types=1);

use App\Support\ProjectSnapshot;

it('exposes the project name, path and toolchain versions it was built with', function (): void {
    $snapshot = new ProjectSnapshot(
        name: 'my-project',
        path: '/path/to/my-project',
        phpBinary: '/path/to/my-project/php',
        phpVersion: '8.5.0',
        laravelVersion: '13.0.0',
    );

    expect($snapshot->name)->toBe('my-project')
        ->and($snapshot->path)->toBe('/path/to/my-project')
        ->and($snapshot->phpBinary)->toBe('/path/to/my-project/php')
        ->and($snapshot->phpVersion)->toBe('8.5.0')
        ->and($snapshot->laravelVersion)->toBe('13.0.0');
});
