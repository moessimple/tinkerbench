<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Browser suite hard cap
|--------------------------------------------------------------------------
|
| tests/Browser is a wiring canary plus a handful of journey guards, not a
| second behavior matrix; Vitest and Pest-unit stay the workhorse. It holds
| at most 10 it() blocks. A new one needs a one-line PR justification that
| the unit layer cannot catch the regression. See .ai/rules/browser.md.
|
*/

it('keeps the browser suite at or under 10 it() blocks', function (): void {
    $count = 0;

    foreach (glob(__DIR__.'/../Browser/*Test.php') ?: [] as $file) {
        $count += preg_match_all('/^it\(/m', (string) file_get_contents($file));
    }

    expect($count)->toBeLessThanOrEqual(
        10,
        "tests/Browser has {$count} it() blocks; the cap is 10 (see .ai/rules/browser.md).",
    );
});
