<?php

declare(strict_types=1);

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;
use Tinkerbench\Runner\FeedItems\FeedItem;
use Tinkerbench\Runner\FeedItems\ViewFeedItem;
use Tinkerbench\Runner\ValueRenderer;
use Tinkerbench\Runner\Watchers\ViewWatcher;

// ValueRenderer has its own test (ValueRendererTest); mocked here so this test only proves
// ViewWatcher's own wiring.

/**
 * A throwaway Blade file backing a real Illuminate\View\View, so the watcher observes actual
 * getPath()/getData() values instead of a hand-built double. Never rendered, only composed
 * (fireing 'composing:*' by hand), so its content is irrelevant.
 *
 * @param  array<string, mixed>  $data
 */
function composableView(array $data = []): View
{
    $path = tempnam(sys_get_temp_dir(), 'view').'.blade.php';
    file_put_contents($path, 'ok');

    return ViewFactory::file($path, $data);
}

it('emits a view item with the path and rendered data, without a line of its own', function (): void {
    $renderer = Mockery::mock(ValueRenderer::class);
    $renderer->shouldReceive('render')->once()->with(['x' => 1])->andReturn('<tree/>');
    $renderer->shouldReceive('renderText')->once()->with(['x' => 1])->andReturn('array:1 [ …');

    $view = composableView(['x' => 1]);

    $emitted = [];
    (new ViewWatcher($renderer))->register(app(), function (FeedItem $item) use (&$emitted): void {
        $emitted[] = $item;
    });

    event('composing: '.$view->getPath(), [$view]);

    unlink($view->getPath());

    expect($emitted)->toHaveCount(1)
        ->and($emitted[0])->toBeInstanceOf(ViewFeedItem::class)
        ->and($emitted[0]->toArray())->toBe([
            'kind' => 'view',
            'path' => $view->getPath(),
            'data_html' => '<tree/>',
            'data_text' => 'array:1 [ …',
            'line' => null,
        ]);
});

it('filters framework-internal keys out of the view data before rendering it', function (): void {
    $renderer = Mockery::mock(ValueRenderer::class);
    $renderer->shouldReceive('render')->once()->with(['x' => 1])->andReturn('<tree/>');
    $renderer->shouldReceive('renderText')->once()->with(['x' => 1])->andReturn('array:1 [ …');

    $view = composableView([
        'x' => 1,
        'app' => app(),
        '__env' => resolve(Factory::class),
        'obLevel' => 1,
        'errors' => new ViewErrorBag(),
    ]);

    $emitted = [];
    (new ViewWatcher($renderer))->register(app(), function (FeedItem $item) use (&$emitted): void {
        $emitted[] = $item;
    });

    event('composing: '.$view->getPath(), [$view]);

    unlink($view->getPath());

    expect($emitted)->toHaveCount(1);
});

it('reacts to any composed view via the composing wildcard event', function (): void {
    $renderer = Mockery::mock(ValueRenderer::class);
    $renderer->shouldReceive('render')->twice()->andReturn('<tree/>');
    $renderer->shouldReceive('renderText')->twice()->andReturn('array:0 [ …');

    $emitted = [];
    (new ViewWatcher($renderer))->register(app(), function (FeedItem $item) use (&$emitted): void {
        $emitted[] = $item;
    });

    $first = composableView();
    $second = composableView();

    event('composing: '.$first->getPath(), [$first]);
    event('composing: '.$second->getPath(), [$second]);

    unlink($first->getPath());
    unlink($second->getPath());

    expect($emitted)->toHaveCount(2);
});
