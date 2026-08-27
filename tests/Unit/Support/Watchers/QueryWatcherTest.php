<?php

declare(strict_types=1);

use App\Support\SourceLocator;
use App\Support\Watchers\QueryWatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * @return list<array<string, mixed>>
 */
function captureQueryItems(SourceLocator $source, QueryExecuted $query): array
{
    $emitted = [];

    new QueryWatcher($source)->register(app(), function (array $item) use (&$emitted): void {
        $emitted[] = $item;
    });

    event($query);

    return $emitted;
}

it('emits a query item with the documented shape', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(12);

    $query = new QueryExecuted('select * from "users" where "id" = ?', [5], 4.2, DB::connection());

    $items = captureQueryItems($source, $query);

    expect($items)->toHaveCount(1)
        ->and($items[0])->toBe([
            'kind' => 'query',
            'sql' => 'select * from "users" where "id" = 5',
            'duration_str' => '4.20ms',
            'connection' => DB::connection()->getName(),
            'slow' => false,
            'duplicate' => false,
            'line' => 12,
        ]);
});

it('flags a query slower than the threshold', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(1);

    $query = new QueryExecuted('select 1', [], 250.0, DB::connection());

    $items = captureQueryItems($source, $query);

    expect($items[0]['slow'])->toBeTrue()
        ->and($items[0]['duration_str'])->toBe('250.00ms');
});

it('leaves the line null when the source has no snippet frame', function (): void {
    $source = Mockery::mock(SourceLocator::class);
    $source->shouldReceive('snippetLine')->andReturn(null);

    $query = new QueryExecuted('select 1', [], 1.0, DB::connection());

    $items = captureQueryItems($source, $query);

    expect($items[0]['line'])->toBeNull();
});
