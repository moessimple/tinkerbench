<?php

declare(strict_types=1);

namespace App\Actions;

use App\Support\Herd;
use App\Support\SnippetRunResult;
use RuntimeException;

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
