<?php

declare(strict_types=1);

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Tinkerbench\Runner\FeedItems\FeedItem;
use Tinkerbench\Runner\FeedItems\HttpClientFeedItem;
use Tinkerbench\Runner\Watchers\HttpClientWatcher;

/**
 * @return list<FeedItem>
 */
function captureHttpClientItems(callable $makeRequest): array
{
    $emitted = [];

    (new HttpClientWatcher())->register(app(), function (FeedItem $item) use (&$emitted): void {
        $emitted[] = $item;
    });

    $makeRequest();

    return $emitted;
}

it('emits an http_client item built from the request and response, without a line of its own', function (): void {
    Http::fake([
        'https://example.test/*' => Http::response('{"id":1}', 200, ['Content-Type' => 'application/json']),
    ]);

    $items = captureHttpClientItems(fn () => Http::withHeaders(['Authorization' => 'Bearer secret'])->get('https://example.test/users'));

    expect($items)->toHaveCount(1)
        ->and($items[0])->toBeInstanceOf(HttpClientFeedItem::class);

    $array = $items[0]->toArray();

    expect($array)->toMatchArray([
        'kind' => 'http_client',
        'method' => 'GET',
        'url' => 'https://example.test/users',
        'faked' => true,
        'status' => 200,
        'response_body_preview' => '{"id":1}',
        'response_truncated' => false,
        'response_content_type' => 'application/json',
        'response_size' => null,
        'line' => null,
    ])->and($array['request_headers']['Authorization'])->toBe(['Bearer secret'])
        ->and($array['duration_ms'])->toBeFloat()->toBeGreaterThanOrEqual(0.0);
});

it('captures the request body and content type alongside the response', function (): void {
    Http::fake([
        'https://example.test/*' => Http::response('ok', 200),
    ]);

    $items = captureHttpClientItems(
        fn () => Http::withHeaders(['Content-Type' => 'application/json'])
            ->withBody('{"name":"Ada"}', 'application/json')
            ->post('https://example.test/users'),
    );

    $array = $items[0]->toArray();

    expect($array['request_body_preview'])->toBe('{"name":"Ada"}')
        ->and($array['request_content_type'])->toBe('application/json')
        ->and($array['request_type'])->toBe('Json');
});

it('reports faked as false for a request the transport answered with handler stats', function (): void {
    $transport = function (RequestInterface $request, array $options): FulfilledPromise {
        $response = new Psr7Response(200, [], 'ok');
        $options['on_stats'](new TransferStats($request, $response, 0.05, null, ['total_time' => 0.05]));

        return new FulfilledPromise($response);
    };

    $items = captureHttpClientItems(fn () => Http::setHandler($transport)->get('https://example.test/users'));

    expect($items)->toHaveCount(1)
        ->and($items[0]->toArray()['faked'])->toBeFalse();
});

it('emits one item per request when multiple requests happen in the same run', function (): void {
    Http::fake([
        'https://example.test/*' => Http::response('ok', 200),
    ]);

    $items = captureHttpClientItems(function (): void {
        Http::get('https://example.test/one');
        Http::get('https://example.test/two');
    });

    expect($items)->toHaveCount(2)
        ->and($items[0]->toArray()['url'])->toBe('https://example.test/one')
        ->and($items[1]->toArray()['url'])->toBe('https://example.test/two');
});

it('emits one item for every network request a redirect chain makes, in order', function (): void {
    Http::fake([
        'https://example.test/old' => Http::response('', 301, ['Location' => 'https://example.test/new']),
        'https://example.test/new' => Http::response('ok', 200),
    ]);

    $items = captureHttpClientItems(fn () => Http::get('https://example.test/old'));

    expect(array_map(fn (FeedItem $item): array => [$item->toArray()['url'], $item->toArray()['status']], $items))->toBe([
        ['https://example.test/old', 301],
        ['https://example.test/new', 200],
    ]);
});

it('leaves the response body readable for the snippet that made the request', function (): void {
    Http::fake([
        'https://example.test/*' => Http::response('{"id":1}', 200),
    ]);

    $body = null;
    captureHttpClientItems(function () use (&$body): void {
        $body = Http::get('https://example.test/users')->body();
    });

    expect($body)->toBe('{"id":1}');
});

it('emits nothing for a request that fails to connect, leaving it to the uncaught-exception path', function (): void {
    Http::fake([
        'https://example.test/*' => fn () => throw new ConnectionException('Connection failed'),
    ]);

    $emitted = [];
    (new HttpClientWatcher())->register(app(), function (FeedItem $item) use (&$emitted): void {
        $emitted[] = $item;
    });

    expect(fn () => Http::get('https://example.test/users'))
        ->toThrow(ConnectionException::class);

    expect($emitted)->toBeEmpty();
});
