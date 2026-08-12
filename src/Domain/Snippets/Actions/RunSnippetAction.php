<?php

declare(strict_types=1);

namespace Domain\Snippets\Actions;

use Support\Herd;
use Support\SnippetRunResult;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RunSnippetAction
{
    public function __construct(private Herd $herd) {}

    public function execute(string $code, string $project): SnippetRunResult
    {
        $projectPath = $this->herd->projectPath($project);

        throw_if($projectPath === null, NotFoundHttpException::class, "Unknown Herd project: {$project}");

        return $this->herd->runSnippet($code, $this->herd->phpBinary($project), $projectPath);
    }
}
