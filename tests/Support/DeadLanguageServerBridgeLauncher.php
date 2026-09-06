<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\LanguageServer\LanguageServerBridgeLauncher;

/**
 * Bridge-launcher double for the browser suite. Both LSP bridges depend on the real
 * launcher, and it is the single seam that spawns `node`; overriding its one method to
 * report port 0 keeps both bridges' real code on the graceful-degrade path
 * (`MonacoEditor.vue` handles an attach failure, covered by `MonacoEditor.test.ts`)
 * without starting a process.
 *
 * Binding this one launcher rather than a separate double per bridge avoids the two
 * bridges' incompatible `start()` signatures (SPEC names a single `DeadLanguageServerBridge`,
 * which cannot extend both).
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
