<?php

declare(strict_types=1);

namespace App\Support;

class LanguageServerBridge
{
    public function __construct(private LanguageServerBridgeLauncher $launcher) {}

    public function start(string $projectPath, string $phpVersion): int
    {
        return $this->launcher->start(base_path('app/Support/bin/intelephense-bridge.mjs'), [$projectPath, $phpVersion]);
    }
}
