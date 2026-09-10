<?php

declare(strict_types=1);

namespace App\Actions;

use App\Support\Herd;
use App\Support\SnippetRun\SnippetRunner;
use App\Support\SnippetRun\SnippetRunResult;

class RunSnippetAction
{
    public function __construct(private Herd $herd, private SnippetRunner $runner) {}

    public function handle(string $project, string $code): SnippetRunResult
    {
        $projectPath = $this->herd->projectPathOrFail($project);

        return $this->runner->run($code, $this->herd->phpBinary($project), $projectPath);
    }
}
