<?php

declare(strict_types=1);

namespace App\Support\LanguageServer;

class LaravelLspBridge
{
    public function __construct(private LanguageServerBridgeLauncher $launcher) {}

    public function start(string $projectPath, string $phpBinary, string $targetPhpBinary): int
    {
        return $this->launcher->start(base_path('app/Support/bin/laravel-lsp-bridge.mjs'), [$projectPath, $phpBinary, $targetPhpBinary]);
    }
}
