<?php

declare(strict_types=1);

namespace Tests\TestSupport\PHPStan;

use Pest\Expectation;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

/**
 * Teaches PHPStan about the custom expectations registered in tests/Pest.php through
 * expect()->extend(). pest-plugin-phpstan resolves Pest's built-in expectation API but not
 * user extensions, so without this every expect(...)->toUseMiddleware(...) call in the Http
 * suite is an "undefined method on Pest\Expectation" error.
 *
 * The method list mirrors the expect()->extend() calls in tests/Pest.php and must be kept in
 * sync with them.
 */
final class PestCustomExpectationMethodsExtension implements MethodsClassReflectionExtension
{
    /** @var list<string> */
    private const array METHODS = ['toBeOne', 'toUseType', 'toUseFormRequest', 'toUseMiddleware'];

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return $classReflection->getName() === Expectation::class
            && in_array($methodName, self::METHODS, true);
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return new PestCustomExpectationMethodReflection($classReflection, $methodName);
    }
}
