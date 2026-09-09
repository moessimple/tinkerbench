<?php

declare(strict_types=1);

use App\Support\Herd;
use App\Support\ProjectSnapshot;
use Composer\Autoload\ClassLoader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\MiddlewareNameResolver;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

use function Pest\Laravel\mock;
use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| App\ namespace ownership
|--------------------------------------------------------------------------
|
| laravel/pint and laravel/lsp are Laravel Zero CLI tools that register their own App\ PSR-4
| directories (vendor/laravel/{pint,lsp}/app) into the shared autoloader, colliding with this
| project's App\ namespace. Non-parallel, pest-plugin-arch's preset scans tolerate the duplicate
| classes; under `pest --parallel` a worker resolves e.g. App\Providers\AppServiceProvider to the
| path-less vendor copy and crashes with "$path must not be accessed before initialization".
| Nothing in the suite loads those vendor App\ classes (both tools run as subprocesses), so point
| App\ back at this project's app/ for the whole test run, workers included.
|
*/

/** @var ClassLoader $loader */
$loader = require dirname(__DIR__).'/vendor/autoload.php';
$loader->setPsr4('App\\', [dirname(__DIR__).'/app']);

/*
|--------------------------------------------------------------------------
| Test Case and deterministic state
|--------------------------------------------------------------------------
|
| Unit, Http, Console and Browser tests start from a known baseline: real random strings and
| UUIDs (undoing a fake a previous test forgot to reset), a hard failure on any unfaked
| outbound process (the twin of essentials' PreventStrayRequests), a fresh faked default
| filesystem disk, and frozen time so time-based assertions do not race the clock.
|
| LazilyRefreshDatabase sits on top: it migrates and opens its transaction only when a test
| touches the database. Browser is included because pest-plugin-browser runs the app
| in-process through the same container and connection as the test (no artisan serve), so
| the transaction and the fakes reach browser-driven requests. Browser tests also join the
| `browser` group, which a bare pest or artisan test run excludes (see phpunit.xml).
|
| Arch tests are not extended here: they are static assertions over the codebase, need no
| TestCase, and never open a connection. They run via the Arch testsuite in phpunit.xml.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Process::preventStrayProcesses();
        Storage::fake();

        $this->freezeTime();
    })
    ->in('Unit', 'Http', 'Console', 'Browser');

// Pest's BootFiles bootstrapper only auto-includes the root tests/Pest.php, never a
// nested one. The Browser suite keeps its own setup (snippets-disk isolation,
// external-process fakes, forced Vite manifest) in tests/Browser/Pest.php; pull it in from here.
require_once __DIR__.'/Browser/Pest.php';

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
    assertInvokableDeclaresParameterType($this->value, $type);

    return $this;
});

/**
 * Like toUseType(), plus proves the type is actually a FormRequest, not just a class that
 * happens to share its name.
 */
expect()->extend('toUseFormRequest', function (string $type): self {
    Assert::assertTrue(is_subclass_of($type, FormRequest::class), "{$type} is not a FormRequest.");
    assertInvokableDeclaresParameterType($this->value, $type);

    return $this;
});

/**
 * Proves an invokable controller's route is wired with the given middleware class, by
 * resolving the route registered for that controller and resolving every middleware name
 * it gathers (including names from middleware groups) down to concrete class names.
 */
expect()->extend('toUseMiddleware', function (string $middleware): self {
    assertInvokableRouteUsesMiddleware($this->value, $middleware);

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
 * Backs the toUseType() / toUseFormRequest() expectations: proves $target's __invoke()
 * declares a parameter of $type. $target is whatever expect() wrapped, a controller
 * class-string in practice; anything that is not a class-string or object fails the test.
 */
function assertInvokableDeclaresParameterType(mixed $target, string $type): void
{
    if (! is_string($target) && ! is_object($target)) {
        Assert::fail('expect()->toUseType() needs a class-string or object.');
    }

    $usesType = collect(new ReflectionMethod($target, '__invoke')->getParameters())->contains(
        fn (ReflectionParameter $parameter): bool => $parameter->getType() instanceof ReflectionNamedType
            && $parameter->getType()->getName() === $type
    );

    $target = is_string($target) ? $target : $target::class;

    Assert::assertTrue($usesType, "{$target}::__invoke() has no parameter of type {$type}.");
}

/**
 * Backs the toUseMiddleware() expectation: proves the route registered for the invokable
 * $controller gathers $middleware, resolving middleware-group names down to concrete classes.
 */
function assertInvokableRouteUsesMiddleware(mixed $controller, string $middleware): void
{
    if (! is_string($controller)) {
        Assert::fail('expect()->toUseMiddleware() needs a controller class-string.');
    }

    $router = resolve(Router::class);
    $route = $router->getRoutes()->getByAction($controller);

    if (! $route instanceof RoutingRoute) {
        Assert::fail("No route is registered for {$controller}.");
    }

    $resolved = collect($route->gatherMiddleware())
        ->flatMap(fn (mixed $name): array => is_string($name) ? Arr::wrap(
            MiddlewareNameResolver::resolve($name, $router->getMiddleware(), $router->getMiddlewareGroups())
        ) : [])
        ->all();

    Assert::assertContains($middleware, $resolved, "{$controller}'s route does not use {$middleware}.");
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

    return postJson('form-request-under-test', $payload);
}

/**
 * Stubs Herd::projectPath() so the given project resolves to a real-looking path. Every route
 * under api/projects/{project}/snippets sits behind EnsureKnownProject, so a
 * controller test for one of those routes needs this unless it's testing the unknown-project
 * case itself.
 */
function mockKnownProject(string $project = 'my-project'): void
{
    mock(Herd::class)
        ->shouldReceive('projectPath')->with($project)->andReturn("/path/to/{$project}");
}

/** A ready-made Herd::snapshotProject() return value for tests that don't care about the exact values. */
function projectSnapshot(string $name = 'my-project'): ProjectSnapshot
{
    return new ProjectSnapshot(
        name: $name,
        path: "/path/to/{$name}",
        phpBinary: '/path/to/php',
        phpVersion: '8.5.0',
        laravelVersion: '13.0.0',
    );
}
