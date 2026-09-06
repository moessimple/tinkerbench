<?php

declare(strict_types=1);

namespace App\Support;

readonly class ProjectSnapshot
{
    public function __construct(
        public string $name,
        public string $path,
        public string $phpBinary,
        public string $phpVersion,
        public string $laravelVersion,
    ) {}
}
