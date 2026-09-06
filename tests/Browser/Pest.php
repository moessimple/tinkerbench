<?php

declare(strict_types=1);

use App\Support\Herd;
use App\Support\LanguageServer\LanguageServerBridgeLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\DeadLanguageServerBridgeLauncher;
use Tests\Support\FakeHerd;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Browser suite bootstrap
|--------------------------------------------------------------------------
|
| Pest's BootFiles bootstrapper only auto-includes the root tests/Pest.php, so this file
| is pulled in from there with require_once. It makes every browser test deterministic:
| an isolated snippets disk, a Herd that never shells out, and LSP bridges that spawn no
| node process. The Amp HTTP server runs in the same PHP process as the test (see
| tasks/plan.md "Open questions (resolved in T2)"), so a beforeEach config() override and
| container bind both reach the browser-driven request.
|
*/

$snippetsRoot = null;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () use (&$snippetsRoot): void {
        $snippetsRoot = sys_get_temp_dir().'/tinkerbench-browser-snippets-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($snippetsRoot);
        config(['filesystems.disks.snippets.root' => $snippetsRoot]);

        $this->app->bind(Herd::class, FakeHerd::class);
        $this->app->bind(LanguageServerBridgeLauncher::class, DeadLanguageServerBridgeLauncher::class);
    })
    ->afterEach(function () use (&$snippetsRoot): void {
        if (is_string($snippetsRoot) && File::isDirectory($snippetsRoot)) {
            File::deleteDirectory($snippetsRoot);
        }

        $snippetsRoot = null;
    })
    ->in(__DIR__);

/**
 * Injects the same transitions/animations kill-switch the plugin uses for screenshot
 * assertions. pest-plugin-browser exposes no suite-wide page-preparation hook, so each
 * test applies it explicitly right after visit(); the page is returned for chaining.
 */
function stopAnimations(mixed $page): mixed
{
    $page->script(
        "if (!document.getElementById('pest-no-animations')) {"
        ."const s = document.createElement('style');"
        ."s.id = 'pest-no-animations';"
        ."s.textContent = '*, *::before, *::after { transition: none !important; animation: none !important; }';"
        .'document.head.appendChild(s); }'
    );

    return $page;
}
