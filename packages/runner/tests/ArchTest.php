<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

// SourceLocator::snippetLine() uses debug_backtrace() as the mechanism for attributing a captured
// dump/query/log to its snippet line, not as a debug leftover, so the php preset allows it. (The
// preset only misfires on it under `pest --parallel`; non-parallel it is not flagged. See root
// tests/ArchTest.php for the same note.)
arch()->preset()->php()->ignoring('debug_backtrace');
arch()->preset()->security();

arch('no class is final')
    ->expect('Tinkerbench\Runner')
    ->classes()
    ->not->toBeFinal();

/*
|--------------------------------------------------------------------------
| Test Coverage
|--------------------------------------------------------------------------
|
| Every class under src/ gets a matching unit test at the same relative path
| under tests/, e.g. src/Watchers/QueryWatcher.php mirrors
| tests/Watchers/QueryWatcherTest.php. Mirrors root tests/ArchTest.php's
| own mirror rule, scoped to this package's single src/ root.
|
*/

it('mirrors every class under src/ with its own unit test', function (): void {
    $root = dirname(__DIR__);

    foreach (Finder::create()->files()->in("{$root}/src")->name('*.php') as $file) {
        if (! preg_match('/^\s*(final\s+|readonly\s+|abstract\s+)*(class|enum|interface|trait)\s/m', $file->getContents())) {
            continue;
        }

        $testPath = 'tests/'.preg_replace('/\.php$/', 'Test.php', $file->getRelativePathname());

        expect(file_exists("{$root}/{$testPath}"))->toBeTrue("Missing {$testPath}");
    }
});
