<?php

declare(strict_types=1);

use Tinkerbench\Runner\ExceptionMapper;
use Tinkerbench\Runner\FeedItems\ExceptionFeedItem;

/**
 * A RuntimeException thrown through a framework (vendor) call, so the trace carries both
 * a frame in this file and vendor frames (Illuminate\Support\Collection).
 */
function nestedRuntimeException(): RuntimeException
{
    try {
        collect([1])->each(fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException $runtimeException) {
        return $runtimeException;
    }

    throw new LogicException('the callback above must throw');
}

/**
 * Treats this test file as the snippet, so the frame it throws from is the "snippet" frame.
 * base_path() is not this package's own root under Orchestra\Testbench\TestCase (it resolves to
 * Testbench's disposable skeleton app instead), so ExceptionMapper needs this package's real root
 * to correctly classify frames under its own vendor/ as vendor frames.
 */
function mapper(): ExceptionMapper
{
    return new ExceptionMapper(dirname(__DIR__), (string) realpath(__FILE__));
}

it('returns an ExceptionFeedItem', function (): void {
    expect(mapper()->toItem(nestedRuntimeException(), 7))->toBeInstanceOf(ExceptionFeedItem::class);
});

it('maps a throwable to the exception feed-item shape', function (): void {
    $item = mapper()->toItem(nestedRuntimeException(), 7)->toArray();

    expect($item['kind'])->toBe('exception')
        ->and($item['type'])->toBe(RuntimeException::class)
        ->and($item['message'])->toBe('boom')
        ->and($item['line'])->toBe(7)
        ->and($item['frames'])->not->toBeEmpty();
});

it('gives every frame the same flat shape without an inline code excerpt', function (): void {
    $item = mapper()->toItem(nestedRuntimeException(), null)->toArray();

    foreach ($item['frames'] as $frame) {
        expect(array_keys($frame))->toBe(['file', 'line', 'function', 'vendor', 'snippet'])
            ->and($frame['vendor'])->toBeBool()
            ->and($frame['snippet'])->toBeBool();
    }
});

it('flags frames outside the application as vendor frames', function (): void {
    $item = mapper()->toItem(nestedRuntimeException(), null)->toArray();

    $vendor = array_filter($item['frames'], fn (array $frame): bool => $frame['vendor']);

    expect($vendor)->not->toBeEmpty();
});

it('marks the frame that sits in the snippet file', function (): void {
    $item = mapper()->toItem(nestedRuntimeException(), null)->toArray();

    $snippetFrames = array_values(array_filter($item['frames'], fn (array $frame): bool => $frame['snippet']));

    expect($snippetFrames)->not->toBeEmpty()
        ->and($snippetFrames[0]['file'])->toBe((string) realpath(__FILE__))
        ->and($snippetFrames[0]['vendor'])->toBeFalse();
});

it('omits frames entirely when frame collection is disabled', function (): void {
    $item = mapper()->toItem(nestedRuntimeException(), null, false)->toArray();

    expect($item['frames'])->toBe([])
        ->and($item['kind'])->toBe('exception')
        ->and($item['message'])->toBe('boom');
});
