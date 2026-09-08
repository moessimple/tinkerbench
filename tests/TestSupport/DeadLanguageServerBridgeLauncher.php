<?php

declare(strict_types=1);

namespace Tests\TestSupport;

use App\Support\LanguageServer\LanguageServerBridgeLauncher;

/**
 * Bridge-launcher double for the browser suite. Both LSP bridges share this launcher, and
 * it is the single seam that spawns `node`; reporting port 0 from its one method keeps both
 * bridges' real code on the graceful-degrade path (`MonacoEditor.vue` handles an attach
 * failure, covered by `MonacoEditor.test.ts`) without starting a process.
 */
class DeadLanguageServerBridgeLauncher extends LanguageServerBridgeLauncher
{
    /**
     * @param  list<string>  $args
     */
    public function start(string $scriptPath, array $args): int
    {
        return 0;
    }
}
