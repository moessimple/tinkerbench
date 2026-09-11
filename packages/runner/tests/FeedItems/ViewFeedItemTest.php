<?php

declare(strict_types=1);

use Tinkerbench\Runner\FeedItems\ViewFeedItem;

it('serializes to the view feed-item shape', function (): void {
    $item = new ViewFeedItem('/app/resources/views/welcome.blade.php', '<pre>x</pre>', 'x');
    $item->line = 3;

    expect($item->toArray())->toBe([
        'kind' => 'view',
        'path' => '/app/resources/views/welcome.blade.php',
        'data_html' => '<pre>x</pre>',
        'data_text' => 'x',
        'line' => 3,
    ]);
});

it('serializes a null line when none was stamped', function (): void {
    expect((new ViewFeedItem('/x.blade.php', '<a/>', 'a'))->toArray()['line'])->toBeNull();
});
