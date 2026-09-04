<?php

declare(strict_types=1);

use App\Support\FeedItems\ResultFeedItem;

it('serializes to the result feed-item shape', function (): void {
    expect(new ResultFeedItem('<pre>42</pre>')->toArray())->toBe([
        'kind' => 'result',
        'html' => '<pre>42</pre>',
    ]);
});
