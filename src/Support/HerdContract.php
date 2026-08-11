<?php

declare(strict_types=1);

namespace Support;

interface HerdContract
{
    public function phpBinary(): string;

    public function runSnippet(string $code): SnippetRunResult;
}
