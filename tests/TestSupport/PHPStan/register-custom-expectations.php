<?php

declare(strict_types=1);

/*
 * Registers the names of tests/Pest.php's custom expect()->extend() matchers so
 * pest-plugin-phpstan's higher-order detection recognizes them: isKnownExpectationMethod()
 * calls Pest\Expectation::hasMethod(), which consults the extends registry. Without this,
 * >= 5.2.1 treats expect(SomeController::class)->toUseMiddleware(...) as a higher-order call
 * and tries to resolve the method on the string value type, which throws an internal error.
 * The real closures registered when the suite runs replace these no-ops.
 */

use Pest\Expectation;

foreach (['toBeOne', 'toUseType', 'toUseFormRequest', 'toUseMiddleware'] as $name) {
    new Expectation(null)->extend($name, fn (): null => null);
}
