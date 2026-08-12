<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\MiddlewareNameResolver;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;
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

/**
 * Proves an invokable controller's route is wired with the given middleware class, by
 * resolving the route registered for that controller and resolving every middleware name
 * it gathers (including names from middleware groups) down to concrete class names.
 */
expect()->extend('toUseMiddleware', function (string $middleware): self {
    $router = resolve(Router::class);
    $route = $router->getRoutes()->getByAction($this->value);

    Assert::assertNotNull($route, "No route is registered for {$this->value}.");

    $resolved = collect($route->gatherMiddleware())
        ->map(fn (string $name): array => Arr::wrap(
            MiddlewareNameResolver::resolve($name, $router->getMiddleware(), $router->getMiddlewareGroups())
        ))
        ->flatten()
        ->all();

    Assert::assertContains($middleware, $resolved, "{$this->value}'s route does not use {$middleware}.");

    return $this;
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

/**
 * Resolves a FormRequest through a real HTTP request against a throwaway route, so its
 * validation runs for real (including any authorize()/prepareForValidation() a request
 * declares) instead of validating an extracted rules() array in isolation.
 *
 * @param  class-string<FormRequest>  $requestClass
 * @param  array<string, mixed>  $payload
 * @return TestResponse<Response>
 */
function createFormRequest(string $requestClass, array $payload = []): TestResponse
{
    Route::post('form-request-under-test', fn () => resolve($requestClass));

    return test()->postJson('form-request-under-test', $payload);
}
