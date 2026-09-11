<?php

declare(strict_types=1);

namespace Tinkerbench\Runner\Watchers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\View\View;
use Tinkerbench\Runner\FeedItems\ViewFeedItem;
use Tinkerbench\Runner\ValueRenderer;

class ViewWatcher implements Watcher
{
    /**
     * Framework-internal keys ManagesEvents::callComposer() and View::gatherData() add to every
     * view's data, not values the snippet itself passed in.
     */
    private const NOISE_KEYS = ['app', '__env', 'obLevel', 'errors'];

    public function __construct(private readonly ValueRenderer $renderer) {}

    public function register(Application $app, callable $emit): void
    {
        // Laravel has no typed event for view composition; 'composing: '.$view->name() is a string
        // event per view name (ManagesEvents::callComposer()), so the wildcard is the only way to
        // observe every rendered view without knowing its name in advance.
        $app->make(Dispatcher::class)->listen('composing:*', function (string $event, array $data) use ($emit): void {
            /** @var View $view */
            $view = $data[0];

            $viewData = array_diff_key($view->getData(), array_flip(self::NOISE_KEYS));

            $emit(new ViewFeedItem(
                $view->getPath(),
                $this->renderer->render($viewData),
                $this->renderer->renderText($viewData),
            ));
        });
    }
}
