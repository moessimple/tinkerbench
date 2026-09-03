<?php

declare(strict_types=1);

use App\Support\SourceLocator;
use App\Support\ValueRenderer;
use App\Support\Watchers\DumpWatcher;
use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\VarDumper\VarDumper;

afterEach(function (): void {
    VarDumper::setHandler(null);
});

it('emits a dump item with the rendered html and the snippet line', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(42);

    $renderer = Mockery::mock(ValueRenderer::class);
    $renderer->shouldReceive('render')->with('hello', null)->andReturn('<rendered/>');

    $emitted = [];
    new DumpWatcher($source, $renderer)->register(
        Mockery::mock(Application::class),
        function (array $item) use (&$emitted): void {
            $emitted[] = $item;
        },
    );

    dump('hello');

    expect($emitted)->toBe([
        ['kind' => 'dump', 'html' => '<rendered/>', 'line' => 42],
    ]);
});

it('does not write the dump to stdout', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(null);

    $renderer = Mockery::mock(ValueRenderer::class);
    $renderer->shouldReceive('render')->andReturn('<rendered/>');

    new DumpWatcher($source, $renderer)->register(
        Mockery::mock(Application::class),
        function (array $item): void {},
    );

    ob_start();
    dump('hello');
    $output = ob_get_clean();

    expect($output)->toBe('');
});
