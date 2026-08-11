<?php

declare(strict_types=1);

namespace Domain\Snippets\Actions;

use Support\HerdContract;
use Support\SnippetRunResult;

final readonly class RunSnippetAction
{
    public function __construct(private HerdContract $herd) {}

    public function execute(string $code): SnippetRunResult
    {
        return $this->herd->runSnippet($code);
    }
}
