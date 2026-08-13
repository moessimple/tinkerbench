<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Baseline Presets
|--------------------------------------------------------------------------
|
| Pest's built-in presets catch generic PHP/security smells (eval, weak
| randomness, debug output) that apply to the whole codebase, regardless
| of layer. The Laravel preset is skipped on purpose: its rules are
| hardcoded to the App\ namespace, which only holds framework glue here,
| not our business code, so it would silently check nothing useful.
|
*/

arch()->preset()->php();
arch()->preset()->security();

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

arch('no class is final')
    ->expect(['App', 'Domain', 'Application', 'Support'])
    ->classes()
    ->not->toBeFinal();

/*
|--------------------------------------------------------------------------
| Layer Dependencies
|--------------------------------------------------------------------------
|
| Application may depend on Domain, never the reverse. Domain knows
| nothing about HTTP or routing. Support is the bottom layer, used by
| both, depending on neither.
|
*/

arch('domain does not depend on application')
    ->expect('Domain')
    ->not->toUse('Application');

arch('domain does not depend on the http/routing framework layer')
    ->expect('Domain')
    ->not->toUse(['Illuminate\Http', 'Illuminate\Routing']);

arch('support does not depend on domain or application')
    ->expect('Support')
    ->not->toUse(['Domain', 'Application']);

/*
|--------------------------------------------------------------------------
| Application Layer
|--------------------------------------------------------------------------
|
| expect('Application\*\...') can't be used below: pest-plugin-arch
| strips the trailing backslash off every registered PSR-4 prefix before
| matching, so "App" (Laravel's own namespace) matches as a false prefix
| of "Application", and with a "*" in the pattern Symfony Finder is
| handed a nonexistent path built from the wrong prefix. Listing each
| area's namespace explicitly (no "*") avoids the wildcard branch that
| triggers the crash. Domain and Support have no such collision, so they
| use "*" freely in their own sections. Adding a new Application area's
| Controllers/Requests/Middleware means adding it here too.
|
*/

arch('controllers are suffixed correctly')
    ->expect(['Application\Snippets\Controllers', 'Application\Projects\Controllers'])
    ->classes()
    ->toHaveSuffix('Controller');

arch('controllers stay single-action')
    ->expect(['Application\Snippets\Controllers', 'Application\Projects\Controllers'])
    ->classes()
    ->not->toHavePublicMethodsBesides(['__construct', '__invoke']);

arch('requests are suffixed correctly')
    ->expect('Application\Snippets\Requests')
    ->classes()
    ->toHaveSuffix('Request');

arch('requests extend FormRequest and declare rules')
    ->expect('Application\Snippets\Requests')
    ->classes()
    ->toExtend(FormRequest::class)
    ->toHaveMethod('rules');

arch('middleware are suffixed correctly')
    ->expect('Application\Projects\Middleware')
    ->classes()
    ->toHaveSuffix('Middleware');

arch('middleware declares handle')
    ->expect('Application\Projects\Middleware')
    ->classes()
    ->toHaveMethod('handle');

/*
|--------------------------------------------------------------------------
| Domain Layer
|--------------------------------------------------------------------------
*/

arch('actions are suffixed correctly')
    ->expect('Domain\*\Actions')
    ->classes()
    ->toHaveSuffix('Action');

arch('actions only expose execute')
    ->expect('Domain\*\Actions')
    ->classes()
    ->not->toHavePublicMethodsBesides(['__construct', 'execute']);

/*
|--------------------------------------------------------------------------
| Test Coverage
|--------------------------------------------------------------------------
|
| Every class in src/Domain, src/Application, src/Support gets a matching
| test at the same relative path under tests/, e.g.
| src/Domain/Snippets/Actions/RunSnippetAction.php mirrors
| tests/Domain/Snippets/Actions/RunSnippetActionTest.php. Files that
| don't declare a class/enum/interface/trait (like the CLI entry script
| under Support/bin) are skipped, they have nothing to unit test directly.
|
*/

it('mirrors every business code class with its own test', function (): void {
    $root = dirname(__DIR__, 2);

    foreach (['Domain', 'Application', 'Support'] as $layer) {
        foreach (Finder::create()->files()->in("{$root}/src/{$layer}")->name('*.php') as $file) {
            if (! preg_match('/^\s*(final\s+|readonly\s+|abstract\s+)*(class|enum|interface|trait)\s/m', $file->getContents())) {
                continue;
            }

            $testPath = "tests/{$layer}/".preg_replace('/\.php$/', 'Test.php', $file->getRelativePathname());

            expect(file_exists("{$root}/{$testPath}"))->toBeTrue("Missing {$testPath}");
        }
    }
});
