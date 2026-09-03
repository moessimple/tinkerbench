<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Baseline Presets
|--------------------------------------------------------------------------
|
| Pest's built-in presets catch generic PHP/security smells (eval, weak
| randomness, debug output) that apply to the whole codebase. The Laravel
| preset applies too: business code lives under App\, so it checks
| controller/request/middleware suffixes and shapes, model conventions, and
| more. It allows __invoke-only controllers, compatible with this project's
| single-action style.
|
*/

// SourceLocator::snippetLine() uses debug_backtrace() as the mechanism for attributing a captured
// dump/query/log to its snippet line, not as a debug leftover, so the php preset allows it. (The
// preset only misfires on it under `pest --parallel`; non-parallel it is not flagged.)
arch()->preset()->php()->ignoring('debug_backtrace');
arch()->preset()->security();
arch()->preset()->laravel();

arch('no class is final')
    ->expect('App')
    ->classes()
    ->not->toBeFinal();

/*
|--------------------------------------------------------------------------
| Test Coverage
|--------------------------------------------------------------------------
|
| Every class in app/Actions, app/Support, app/Enums gets a matching unit
| test at the same relative path under tests/Unit/, e.g.
| app/Actions/RunSnippetAction.php mirrors
| tests/Unit/Actions/RunSnippetActionTest.php. Files that don't
| declare a class/enum/interface/trait (like the CLI entry script under
| Support/bin) are skipped, they have nothing to unit test directly.
|
| Controllers/Requests/Middleware are intentionally not scanned here: they're
| proven through tests/Http/ flow tests instead of a mandatory 1:1 mirror.
|
*/

it('mirrors every business code class with its own unit test', function (): void {
    $root = dirname(__DIR__);

    foreach (['Actions', 'Support', 'Enums'] as $folder) {
        foreach (Finder::create()->files()->in("{$root}/app/{$folder}")->name('*.php') as $file) {
            if (! preg_match('/^\s*(final\s+|readonly\s+|abstract\s+)*(class|enum|interface|trait)\s/m', $file->getContents())) {
                continue;
            }

            $testPath = "tests/Unit/{$folder}/".preg_replace('/\.php$/', 'Test.php', $file->getRelativePathname());

            expect(file_exists("{$root}/{$testPath}"))->toBeTrue("Missing {$testPath}");
        }
    }
});
