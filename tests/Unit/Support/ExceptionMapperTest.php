<?php

declare(strict_types=1);

use App\Support\ExceptionMapper;

/**
 * A RuntimeException thrown through a framework (vendor) call, so the trace carries both
 * application frames (this file) and vendor frames (Illuminate\Support\Collection).
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

it('maps a throwable to the exception feed-item shape', function (): void {
    $item = new ExceptionMapper(base_path())->toItem(nestedRuntimeException(), 7);

    expect($item['kind'])->toBe('exception')
        ->and($item['type'])->toBe(RuntimeException::class)
        ->and($item['message'])->toBe('boom')
        ->and($item['line'])->toBe(7)
        ->and($item['frames'])->not->toBeEmpty();
});

it('flags vendor frames and leaves off their code snippet', function (): void {
    $item = new ExceptionMapper(base_path())->toItem(nestedRuntimeException(), null);

    $vendorFrames = array_values(array_filter($item['frames'], fn (array $frame): bool => $frame['vendor']));

    expect($vendorFrames)->not->toBeEmpty();

    foreach ($vendorFrames as $frame) {
        expect($frame)->toHaveKeys(['file', 'line', 'function', 'vendor'])
            ->and($frame)->not->toHaveKey('snippet');
    }
});

it('omits frames entirely when frame collection is disabled', function (): void {
    $item = new ExceptionMapper(base_path())->toItem(nestedRuntimeException(), null, false);

    expect($item['frames'])->toBe([])
        ->and($item['kind'])->toBe('exception')
        ->and($item['message'])->toBe('boom');
});

it('includes surrounding code context on application frames', function (): void {
    $item = new ExceptionMapper(base_path())->toItem(nestedRuntimeException(), null);

    $withSnippet = array_values(array_filter(
        $item['frames'],
        fn (array $frame): bool => ! $frame['vendor'] && array_key_exists('snippet', $frame),
    ));

    expect($withSnippet)->not->toBeEmpty();

    $snippet = $withSnippet[0]['snippet'];

    expect($snippet)->not->toBeEmpty()
        ->and($snippet[0])->toHaveKeys(['line', 'code'])
        ->and($snippet[0]['line'])->toBeInt();
});
