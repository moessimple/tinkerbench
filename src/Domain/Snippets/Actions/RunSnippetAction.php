<?php

declare(strict_types=1);

namespace Domain\Snippets\Actions;

use Support\Herd;
use Support\SnippetRunResult;

class RunSnippetAction
{
    public function __construct(private Herd $herd) {}

    public function execute(string $code): SnippetRunResult
    {
        return $this->herd->runSnippet($code);
    }
}
