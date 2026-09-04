<?php

declare(strict_types=1);

use App\Support\SnippetRun\FeedItems\FeedItem;
use App\Support\SnippetRun\FeedItems\NPlusOneFeedItem;
use App\Support\SnippetRun\Watchers\LazyLoadWatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

afterEach(function (): void {
    // preventLazyLoading() and the violation callback are process-wide static state; without this
    // reset every later test that lazy-loads a relation would hit this watcher's handler.
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
 * @return list<FeedItem>
 */
function captureLazyLoadItems(Closure $trigger): array
{
    $emitted = [];

    new LazyLoadWatcher()->register(
        Mockery::mock(Application::class),
        function (FeedItem $item) use (&$emitted): void {
            $emitted[] = $item;
        },
    );

    $trigger();

    return $emitted;
}

it('turns lazy loading prevention on', function (): void {
    new LazyLoadWatcher()->register(
        Mockery::mock(Application::class),
        function (FeedItem $item): void {},
    );

    expect(Model::preventsLazyLoading())->toBeTrue();
});

it('emits one n_plus_one item with the model and relation, without a line of its own', function (): void {
    $items = captureLazyLoadItems(function (): void {
        triggerLazyLoadViolation(new Pivot(), 'posts');
    });

    expect($items)->toHaveCount(1)
        ->and($items[0])->toBeInstanceOf(NPlusOneFeedItem::class)
        ->and($items[0]->toArray())->toBe([
            'kind' => 'n_plus_one',
            'model' => Pivot::class,
            'relation' => 'posts',
            'count' => 1,
            'line' => null,
        ]);
});

it('does not throw when a violation happens', function (): void {
    captureLazyLoadItems(function (): void {
        triggerLazyLoadViolation(new Pivot(), 'posts');
    });
})->throwsNoExceptions();
