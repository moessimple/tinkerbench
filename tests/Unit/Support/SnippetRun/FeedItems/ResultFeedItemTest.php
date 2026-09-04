<?php

declare(strict_types=1);

use App\Support\SnippetRun\FeedItems\ResultFeedItem;

it('serializes to the result feed-item shape', function (): void {
    expect(new ResultFeedItem('<pre>42</pre>', '42')->toArray())->toBe([
        'kind' => 'result',
        'html' => '<pre>42</pre>',
        'text' => '42',
    ]);
});
