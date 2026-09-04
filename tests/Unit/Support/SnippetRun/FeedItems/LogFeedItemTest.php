<?php

declare(strict_types=1);

use App\Support\SnippetRun\FeedItems\LogFeedItem;
use App\Support\SnippetRun\ValueRenderer;

it('serializes to the log feed-item shape with the context rendered to html and text', function (): void {
    $renderer = Mockery::mock(ValueRenderer::class);
    $renderer->shouldReceive('render')->once()->with(['free' => '2%'])->andReturn('<tree/>');
    $renderer->shouldReceive('renderText')->once()->with(['free' => '2%'])->andReturn('array:1 [ …');

    $item = new LogFeedItem('warning', 'disk almost full', ['free' => '2%'], $renderer);
    $item->line = 7;

    expect($item->toArray())->toBe([
        'kind' => 'log',
        'label' => 'warning',
        'message' => 'disk almost full',
        'context_html' => '<tree/>',
        'context_text' => 'array:1 [ …',
        'line' => 7,
    ]);
});

it('emits null context and never renders when the context is empty', function (): void {
    $renderer = Mockery::mock(ValueRenderer::class);
    $renderer->shouldNotReceive('render');
    $renderer->shouldNotReceive('renderText');

    $shape = new LogFeedItem('info', 'started', [], $renderer)->toArray();

    expect($shape['context_html'])->toBeNull()
        ->and($shape['context_text'])->toBeNull();
});
