<?php

declare(strict_types=1);

use App\Support\SourceLocator;
use App\Support\Watchers\LazyLoadWatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

afterEach(function (): void {
    // preventLazyLoading() and the violation callback are process-wide static state; without this
    // reset every later test that lazy-loads a relation would blow up on this watcher's handler.
    Model::preventLazyLoading(false);
    Model::handleLazyLoadingViolationUsing(null);
});

/**
 * Fires the framework's lazy-loading violation dispatch (Model::handleLazyLoadingViolation, the
 * exact call getRelationValue() makes) against $model, without a database. The method is protected,
 * so a closure bound into Model's scope stands in for the internal caller.
 */
function triggerLazyLoadViolation(Model $model, string $relation): void
{
    Closure::bind(
        function (string $relation): void {
            $this->handleLazyLoadingViolation($relation);
        },
        $model,
        Model::class,
    )($relation);
}

/**
 * @return list<array<string, mixed>>
 */
function captureLazyLoadItems(SourceLocator $source, Closure $trigger): array
{
    $emitted = [];

    new LazyLoadWatcher($source)->register(
        Mockery::mock(Application::class),
        function (array $item) use (&$emitted): void {
            $emitted[] = $item;
        },
    );

    $trigger();

    return $emitted;
}

it('turns lazy loading prevention on', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(null);

    new LazyLoadWatcher($source)->register(
        Mockery::mock(Application::class),
        function (array $item): void {},
    );

    expect(Model::preventsLazyLoading())->toBeTrue();
});

it('emits one n_plus_one item with the model, relation and snippet line', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(17);

    $model = new Pivot();

    $items = captureLazyLoadItems($source, function () use ($model): void {
        triggerLazyLoadViolation($model, 'posts');
    });

    expect($items)->toBe([
        [
            'kind' => 'n_plus_one',
            'model' => Pivot::class,
            'relation' => 'posts',
            'line' => 17,
        ],
    ]);
});

it('leaves the line null when the source has no snippet frame', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(null);

    $items = captureLazyLoadItems($source, function (): void {
        triggerLazyLoadViolation(new Pivot(), 'comments');
    });

    expect($items[0]['line'])->toBeNull();
});

it('does not throw when a violation happens', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(null);

    captureLazyLoadItems($source, function (): void {
        triggerLazyLoadViolation(new Pivot(), 'posts');
    });
})->throwsNoExceptions();
