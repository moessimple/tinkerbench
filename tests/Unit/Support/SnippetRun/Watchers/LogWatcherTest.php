<?php

declare(strict_types=1);

use App\Support\SnippetRun\FeedItems\FeedItem;
use App\Support\SnippetRun\FeedItems\LogFeedItem;
use App\Support\SnippetRun\ValueRenderer;
use App\Support\SnippetRun\Watchers\LogWatcher;
use Illuminate\Log\Events\MessageLogged;

// ValueRenderer has its own test (ValueRendererTest); mocked here so this test only proves
// LogWatcher's own wiring.

it('emits a log item with the context rendered to html and text, without a line of its own', function (): void {
    $renderer = Mockery::mock(ValueRenderer::class);
    $renderer->shouldReceive('render')->once()->with(['free' => '2%'])->andReturn('<tree/>');
    $renderer->shouldReceive('renderText')->once()->with(['free' => '2%'])->andReturn('array:1 [ …');

    $emitted = [];
    new LogWatcher($renderer)->register(app(), function (FeedItem $item) use (&$emitted): void {
        $emitted[] = $item;
    });

    event(new MessageLogged('warning', 'disk almost full', ['free' => '2%']));

    expect($emitted)->toHaveCount(1)
        ->and($emitted[0])->toBeInstanceOf(LogFeedItem::class)
        ->and($emitted[0]->toArray())->toBe([
            'kind' => 'log',
            'label' => 'warning',
            'message' => 'disk almost full',
            'context_html' => '<tree/>',
            'context_text' => 'array:1 [ …',
            'line' => null,
        ]);
});

it('emits a null context and never renders when the log has no context', function (): void {
    $renderer = Mockery::mock(ValueRenderer::class);
    $renderer->shouldNotReceive('render');
    $renderer->shouldNotReceive('renderText');

    $emitted = [];
    new LogWatcher($renderer)->register(app(), function (FeedItem $item) use (&$emitted): void {
        $emitted[] = $item;
    });

    event(new MessageLogged('info', 'started', []));

    expect($emitted[0]->toArray())->toMatchArray([
        'context_html' => null,
        'context_text' => null,
    ]);
});
