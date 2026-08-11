<?php

declare(strict_types=1);

namespace Support;

use InvalidArgumentException;

final class Herd implements HerdContract
{
    public function phpBinary(): string
    {
        return $this->bin().'/php';
    }

    private function bin(): string
    {
        $path = config('services.herd.bin');

        throw_if(! is_string($path) || $path === '', InvalidArgumentException::class, 'The services.herd.bin configuration must be a non-empty path.');

        return $path;
    }
}
