<?php

declare(strict_types=1);

namespace App\Actions;

use App\Support\Herd;
use App\Support\SnippetRunResult;

class RunSnippetAction
{
    public function __construct(private Herd $herd) {}

    public function execute(string $project, string $code): SnippetRunResult
    {
        $projectPath = $this->herd->projectPathOrFail($project);

        return $this->herd->runSnippet($code, $this->herd->phpBinary($project), $projectPath);
    }
}
