<?php

declare(strict_types=1);

use Symfony\Component\VarDumper\VarDumper;
use Tinkerbench\Runner\DumpCapture;
use Tinkerbench\Runner\ValueRenderer;

afterEach(function (): void {
    VarDumper::setHandler(null);
});

it('forwards each dumped value as rendered html and text', function (): void {
    $renderer = Mockery::mock(ValueRenderer::class);
    $renderer->shouldReceive('render')->with('hello', null)->andReturn('<rendered/>');
    $renderer->shouldReceive('renderText')->with('hello', null)->andReturn('hello');

    $calls = [];
    DumpCapture::install($renderer, function (string $html, string $text) use (&$calls): void {
        $calls[] = [$html, $text];
    });

    dump('hello');

    expect($calls)->toBe([['<rendered/>', 'hello']]);
});

it('does not write the dump to stdout', function (): void {
    $renderer = Mockery::mock(ValueRenderer::class);
    $renderer->shouldReceive('render')->andReturn('<rendered/>');
    $renderer->shouldReceive('renderText')->andReturn('hello');

    DumpCapture::install($renderer, function (): void {});

    ob_start();
    dump('hello');

    expect(ob_get_clean())->toBe('');
});

it('installs the handler even when VAR_DUMPER_FORMAT is set', function (): void {
    $_SERVER['VAR_DUMPER_FORMAT'] = 'html';

    $renderer = Mockery::mock(ValueRenderer::class);
    $renderer->shouldReceive('render')->andReturn('<rendered/>');
    $renderer->shouldReceive('renderText')->andReturn('hello');

    $captured = false;
    DumpCapture::install($renderer, function (string $html, string $text) use (&$captured): void {
        $captured = true;
    });

    dump('hello');

    expect($captured)->toBeTrue()
        ->and($_SERVER)->not->toHaveKey('VAR_DUMPER_FORMAT');
});
