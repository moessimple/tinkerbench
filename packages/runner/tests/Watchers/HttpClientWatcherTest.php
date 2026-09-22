<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
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
        'status' => 200,
        'body_preview' => '{"id":1}',
        'truncated' => false,
        'content_type' => 'application/json',
        'size' => null,
        'line' => null,
    ])->and($array['request_headers']['Authorization'])->toBe(['[REDACTED]'])
        ->and($array['duration_ms'])->toBeFloat()->toBeGreaterThanOrEqual(0.0);
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

it('does not listen for connection failures, leaving them to the uncaught-exception path', function (): void {
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

it('emits nothing for a ResponseReceived with no matching RequestSending start time', function (): void {
    $request = new Request(new Psr7Request('GET', 'https://example.test/orphan'));
    $response = new Response(new Psr7Response(200, [], 'ok'));

    $emitted = [];
    (new HttpClientWatcher())->register(app(), function (FeedItem $item) use (&$emitted): void {
        $emitted[] = $item;
    });

    event(new ResponseReceived($request, $response));

    expect($emitted)->toBeEmpty();
});
