<?php

declare(strict_types=1);

use Tinkerbench\Runner\FeedItems\ResultFeedItem;

it('serializes to the result feed-item shape', function (): void {
    expect((new ResultFeedItem('<pre>42</pre>', '42'))->toArray())->toBe([
        'kind' => 'result',
        'html' => '<pre>42</pre>',
        'text' => '42',
    ]);
});
