<?php

declare(strict_types=1);

namespace App\Actions;

use App\Support\Herd;
use App\Support\LaravelLspBridge;
use RuntimeException;

class StartLaravelLanguageServerAction
{
    public function __construct(private Herd $herd, private LaravelLspBridge $bridge) {}

    public function execute(string $project): int
    {
        $projectPath = $this->herd->projectPath($project);

        throw_if($projectPath === null, RuntimeException::class, "Unknown Herd project: {$project}");

        return $this->bridge->start($projectPath);
    }
}
