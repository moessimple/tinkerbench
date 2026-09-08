<?php

declare(strict_types=1);

namespace Tests\TestSupport;

use App\Support\Herd;
use App\Support\ProjectSnapshot;

/**
 * Herd double for the browser suite. The single project `tinkerbench` maps to this app's
 * own base path, and every value the real Herd resolves by shelling out to `herd.phar` or
 * `php -v` is a constant here. Extends the real Herd so the container binds it in place
 * (`.ai/rules/general.md`: bind the concrete fake, no interface).
 */
class FakeHerd extends Herd
{
    /** @return array<string, string> */
    public function projects(bool $fresh = false): array
    {
        return ['tinkerbench' => base_path()];
    }

    public function projectPath(string $project): ?string
    {
        return $project === 'tinkerbench' ? base_path() : null;
    }

    public function phpBinary(string $project, bool $fresh = false): string
    {
        return PHP_BINARY;
    }

    public function phpVersion(string $phpBinary, bool $fresh = false): string
    {
        return PHP_VERSION;
    }

    public function snapshotProject(?string $project, bool $fresh = false): ?ProjectSnapshot
    {
        if (($project ?? 'tinkerbench') !== 'tinkerbench') {
            return null;
        }

        return new ProjectSnapshot(
            name: 'tinkerbench',
            path: base_path(),
            phpBinary: PHP_BINARY,
            phpVersion: PHP_VERSION,
            laravelVersion: app()->version(),
        );
    }
}
