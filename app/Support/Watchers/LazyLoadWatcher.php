<?php

declare(strict_types=1);

namespace App\Support\Watchers;

use App\Support\FeedItems\NPlusOneFeedItem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;

class LazyLoadWatcher implements Watcher
{
    public function register(Application $app, callable $emit): void
    {
        // preventLazyLoading() only installs the observation hook below; it does not change which
        // queries the snippet runs. The target project's own model config is otherwise left as-is,
        // so a project that batches lazy loads (automatic eager loading) still reports no N+1 here,
        // because it genuinely has none.
        Model::preventLazyLoading();

        // Replaces Laravel's default violation handler, which throws LazyLoadingViolationException.
        // Reporting instead of throwing is intentional: the snippet finishes and every lazy-load
        // access is captured, not just the first, so the feed shows the full N+1 count.
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) use ($emit): void {
            $emit(new NPlusOneFeedItem($model::class, $relation));
        });
    }
}
