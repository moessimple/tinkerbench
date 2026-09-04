<?php

declare(strict_types=1);

use App\Enums\FeedItemKind;

it('maps each kind to the wire value the frontend FeedItem union expects', function (): void {
    expect(array_map(fn (FeedItemKind $kind): string => $kind->value, FeedItemKind::cases()))
        ->toBe(['dump', 'query', 'log', 'n_plus_one', 'exception', 'result']);
});
