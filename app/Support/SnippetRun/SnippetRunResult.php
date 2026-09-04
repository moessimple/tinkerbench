<?php

declare(strict_types=1);

namespace App\Support\SnippetRun;

readonly class SnippetRunResult
{
    /** @param array<array-key, mixed>|null $debug */
    public function __construct(public string $output, public ?array $debug) {}
}
