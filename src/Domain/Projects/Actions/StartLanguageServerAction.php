<?php

declare(strict_types=1);

namespace Domain\Projects\Actions;

use RuntimeException;
use Support\Herd;
use Support\LanguageServerBridge;

class StartLanguageServerAction
{
    public function __construct(private Herd $herd, private LanguageServerBridge $bridge) {}

    public function execute(string $project): int
    {
        $projectPath = $this->herd->projectPath($project);

        throw_if($projectPath === null, RuntimeException::class, "Unknown Herd project: {$project}");

        $phpVersion = $this->herd->phpVersion($this->herd->phpBinary($project));

        return $this->bridge->start($projectPath, $phpVersion);
    }
}
