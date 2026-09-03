<?php

declare(strict_types=1);

namespace App\Support\Watchers;

use App\Support\SourceLocator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;

class LazyLoadWatcher implements Watcher
{
    public function __construct(private SourceLocator $source) {}

    /**
     * @param  callable(array{kind: 'n_plus_one', model: string, relation: string, line: int|null}): void  $emit
     */
    public function register(Application $app, callable $emit): void
    {
        // nunomaduro/essentials turns automatic relationship eager loading on by default. While it
        // is on, a lazy access on a retrieved collection batch-loads the relation before the
        // preventLazyLoading() check runs, so no violation ever fires and the N+1 goes unseen. The
        // run forces it off so the snippet's real query shape is what the feed reports.
        Model::automaticallyEagerLoadRelationships(false);
        Model::preventLazyLoading();

        // Replaces Laravel's default violation handler, which throws LazyLoadingViolationException.
        // Reporting instead of throwing is intentional: the snippet finishes and every lazy-load
        // access is captured, not just the first, so the feed shows the full N+1 count.
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) use ($emit): void {
            $emit([
                'kind' => 'n_plus_one',
                'model' => $model::class,
                'relation' => $relation,
                'line' => $this->source->snippetLine(),
            ]);
        });
    }
}
