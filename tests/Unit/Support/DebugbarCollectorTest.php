<?php

declare(strict_types=1);

use App\Support\DebugbarCollector;
use DebugBar\DataCollector\ExceptionsCollector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

it('collects the queries and timing of the wrapped closure', function (): void {
    $debug = new DebugbarCollector()->collect(app(), function (): void {
        DB::select('select 1 as one');
    });

    expect(data_get($debug, 'queries.count'))->toBe(1)
        ->and(data_get($debug, 'queries.statements.0.sql'))->toContain('select 1')
        ->and(data_get($debug, 'time.measures'))->toHaveCount(1)
        ->and(data_get($debug, 'time.measures.0.label'))->toBe('snippet');
});

it('does not collect queries run before the wrapped closure starts', function (): void {
    DB::select('select 1 as before');

    $debug = new DebugbarCollector()->collect(app(), function (): void {
        //
    });

    expect(data_get($debug, 'queries.count'))->toBe(0);
});

it('includes an exception reported through the passed-in collector without interrupting collection', function (): void {
    $debug = new DebugbarCollector()->collect(app(), function (ExceptionsCollector $exceptions): void {
        try {
            throw new RuntimeException('boom');
        } catch (Throwable $throwable) {
            $exceptions->addThrowable($throwable);
        }
    });

    expect(data_get($debug, 'exceptions.count'))->toBe(1)
        ->and(data_get($debug, 'exceptions.exceptions.0.message'))->toBe('boom')
        ->and(data_get($debug, 'time.measures.0.label'))->toBe('snippet');
});

it('captures a dumped value without changing what gets printed to stdout', function (): void {
    ob_start();

    $debug = new DebugbarCollector()->collect(app(), function (): void {
        dump('captured value');
    });

    $printed = ob_get_clean();

    expect($printed)->toContain('Sfdump(')
        ->and(data_get($debug, 'messages.count'))->toBe(1)
        ->and(data_get($debug, 'messages.messages.0.message'))->toBe('captured value');
});

it('captures every value of a multi-argument dump() call, each labelled by its position', function (): void {
    ob_start();

    $debug = new DebugbarCollector()->collect(app(), function (): void {
        dump('first', 'second');
    });

    ob_get_clean();

    expect(data_get($debug, 'messages.count'))->toBe(2)
        ->and(data_get($debug, 'messages.messages.0.message'))->toBe('first')
        ->and(data_get($debug, 'messages.messages.1.message'))->toBe('second');
});

it('captures a dumped non-string value as readable text, not just for strings', function (): void {
    ob_start();

    $debug = new DebugbarCollector()->collect(app(), function (): void {
        dump(42);
    });

    ob_get_clean();

    expect(data_get($debug, 'messages.messages.0.message'))->toBe('42');
});

it('collects the peak memory usage of the wrapped closure', function (): void {
    $debug = new DebugbarCollector()->collect(app(), function (): void {
        //
    });

    expect(data_get($debug, 'memory.peak_usage'))->toBeInt()->toBeGreaterThan(0)
        ->and(data_get($debug, 'memory.peak_usage_str'))->toBeString()->not->toBeEmpty();
});

it('captures a log message written during the wrapped closure, labelled by level', function (): void {
    $debug = new DebugbarCollector()->collect(app(), function (): void {
        Log::warning('boom');
    });

    expect(data_get($debug, 'logs.count'))->toBe(1)
        ->and(data_get($debug, 'logs.messages.0.message'))->toBe('boom')
        ->and(data_get($debug, 'logs.messages.0.label'))->toBe('warning');
});

it('does not capture a log message written before the wrapped closure starts', function (): void {
    Log::info('before');

    $debug = new DebugbarCollector()->collect(app(), function (): void {
        //
    });

    expect(data_get($debug, 'logs.count'))->toBe(0);
});
