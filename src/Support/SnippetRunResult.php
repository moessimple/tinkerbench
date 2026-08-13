<?php

declare(strict_types=1);

namespace Support;

readonly class SnippetRunResult
{
    public function __construct(public string $output) {}
}
