<?php

declare(strict_types=1);

namespace App\Actions;

use App\Support\Herd;
use App\Support\LanguageServer\LanguageServerBridge;

class StartLanguageServerAction
{
    public function __construct(private Herd $herd, private LanguageServerBridge $bridge) {}

    public function execute(string $project): int
    {
        $projectPath = $this->herd->projectPathOrFail($project);

        $phpVersion = $this->herd->phpVersion($this->herd->phpBinary($project));

        return $this->bridge->start($projectPath, $phpVersion);
    }
}
