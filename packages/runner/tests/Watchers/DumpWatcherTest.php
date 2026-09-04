<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\VarDumper\VarDumper;
use Tinkerbench\Runner\FeedItems\DumpFeedItem;
use Tinkerbench\Runner\FeedItems\FeedItem;
use Tinkerbench\Runner\ValueRenderer;
use Tinkerbench\Runner\Watchers\DumpWatcher;

afterEach(function (): void {
    VarDumper::setHandler(null);
});

it('emits a dump item built from the rendered value, without a line of its own', function (): void {
    $renderer = Mockery::mock(ValueRenderer::class);
    $renderer->shouldReceive('render')->with('hello', null)->andReturn('<rendered/>');
    $renderer->shouldReceive('renderText')->with('hello', null)->andReturn('hello');

    $emitted = [];
    (new DumpWatcher($renderer))->register(
        Mockery::mock(Application::class),
        function (FeedItem $item) use (&$emitted): void {
            $emitted[] = $item;
        },
    );

    dump('hello');

    expect($emitted)->toHaveCount(1)
        ->and($emitted[0])->toBeInstanceOf(DumpFeedItem::class)
        ->and($emitted[0]->toArray())->toBe([
            'kind' => 'dump',
            'html' => '<rendered/>',
            'text' => 'hello',
            'line' => null,
        ]);
});

it('does not write the dump to stdout', function (): void {
    $renderer = Mockery::mock(ValueRenderer::class);
    $renderer->shouldReceive('render')->andReturn('<rendered/>');
    $renderer->shouldReceive('renderText')->andReturn('hello');

    (new DumpWatcher($renderer))->register(
        Mockery::mock(Application::class),
        function (FeedItem $item): void {},
    );

    ob_start();
    dump('hello');
    $output = ob_get_clean();

    expect($output)->toBe('');
});
