<?php

declare(strict_types=1);

use App\Support\Herd;
use App\Support\LanguageServer\LanguageServerBridgeLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Pest\Browser\Api\PendingAwaitablePage;
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
| node process. The Amp HTTP server runs in the same PHP process as the test, so a
| beforeEach config() override and container bind both reach the browser-driven request.
|
*/

$snippetsRoot = null;

// A run-snippet journey spawns the real packages/runner subprocess, and the first spawn in
// a test process pays a one-time cold cost (fresh autoload + Laravel boot) well past the
// plugin's 5s default. Raise the auto-wait ceiling once for the whole suite, with headroom
// for a loaded CI runner.
pest()->browser()->timeout(30_000);

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->group('browser')
    ->beforeEach(function () use (&$snippetsRoot): void {
        $snippetsRoot = sys_get_temp_dir().'/tinkerbench-browser-snippets-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($snippetsRoot);
        config(['filesystems.disks.snippets.root' => $snippetsRoot]);

        // A stale public/hot (a dev server that exited without cleaning up) points @vite at
        // an unreachable dev-server URL, so no JS loads and Monaco never mounts. Force the
        // built manifest for the browser suite.
        Vite::useHotFile(storage_path('framework/testing/browser-suite-no-vite-hmr'));

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
function stopAnimations(PendingAwaitablePage $page): PendingAwaitablePage
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

/**
 * Replaces the Monaco editor's contents with the given PHP by real typing. This build of
 * Monaco takes input through the browser's EditContext, whose target is the focused
 * `.native-edit-context` element, not the vestigial read-only `.ime-text-area` textarea;
 * `type()`/`fill()` on a textarea selector are silent no-ops here. Ctrl/Cmd+A then Delete
 * clears the scratch stub first. The page is returned for chaining.
 */
function typeIntoEditor(PendingAwaitablePage $page, string $php): PendingAwaitablePage
{
    $page->click('.monaco-editor');
    $page->keys('.native-edit-context', ['ControlOrMeta+a', 'Delete']);
    $page->typeSlowly('.native-edit-context', $php, 20);

    return $page;
}
