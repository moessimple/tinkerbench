<?php

declare(strict_types=1);

namespace Domain\Snippets\Actions;

use RuntimeException;
use Support\Herd;
use Support\SnippetRunResult;

class RunSnippetAction
{
    public function __construct(private Herd $herd) {}

    public function execute(string $project, string $code): SnippetRunResult
    {
        $projectPath = $this->herd->projectPath($project);

        throw_if($projectPath === null, RuntimeException::class, "Unknown Herd project: {$project}");

        return $this->herd->runSnippet($code, $this->herd->phpBinary($project), $projectPath);
    }
}
