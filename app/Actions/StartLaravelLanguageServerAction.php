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

        // laravel-lsp is a PHP tool, not the target project's own PHP - this app's own PHP binary
        // runs it, resolved the same way this app already resolves any project's PHP binary,
        // rather than relying on the `#!/usr/bin/env php` shebang. Herd's PATH for web-server
        // processes doesn't reliably include `php` (unlike an interactive shell), so an explicit
        // binary is required.
        $phpBinary = $this->herd->phpBinary($this->herd->currentProject());

        // Separately, laravel-lsp needs the *target* project's own PHP to run artisan commands
        // against it (e.g. resolving real config values) - resolved explicitly here rather than
        // left to laravel-lsp's own `herd which-php` auto-detection, which fails the same way
        // under this nested spawn chain.
        $targetPhpBinary = $this->herd->phpBinary($project);

        return $this->bridge->start($projectPath, $phpBinary, $targetPhpBinary);
    }
}
