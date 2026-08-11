<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->in('Support', 'Domain', 'Application');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/**
 * Proves an invokable class (every controller here is one) declares a dependency of the
 * given type on __invoke(), without instantiating or mocking anything. Use for wiring
 * checks; it proves the type is declared, not that it's used correctly, pair with a
 * behavior-level test for that.
 */
expect()->extend('toUseType', function (string $type): self {
    $parameters = new ReflectionMethod($this->value, '__invoke')->getParameters();

    $usesType = collect($parameters)->contains(
        fn (ReflectionParameter $parameter): bool => $parameter->getType() instanceof ReflectionNamedType
            && $parameter->getType()->getName() === $type
    );

    Assert::assertTrue($usesType, "{$this->value}::__invoke() has no parameter of type {$type}.");

    return $this;
});

/**
 * Like toUseType(), plus proves the type is actually a FormRequest, not just a class that
 * happens to share its name.
 */
expect()->extend('toUseFormRequest', function (string $type): self {
    Assert::assertTrue(is_subclass_of($type, FormRequest::class), "{$type} is not a FormRequest.");

    return $this->toUseType($type);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}
