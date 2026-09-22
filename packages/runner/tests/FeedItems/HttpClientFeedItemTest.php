<?php

declare(strict_types=1);

use Tinkerbench\Runner\FeedItems\HttpClientFeedItem;

function httpClientFeedItem(array $overrides = []): HttpClientFeedItem
{
    $defaults = [
        'method' => 'GET',
        'url' => 'https://example.test/users',
        'status' => 200,
        'durationMs' => 42.5,
        'requestHeaders' => ['Accept' => ['application/json']],
        'responseHeaders' => ['Content-Type' => ['application/json']],
        'body' => '{"id":1}',
        'contentType' => 'application/json',
    ];

    $args = [...$defaults, ...$overrides];

    return new HttpClientFeedItem(...$args);
}

it('serializes to the http_client feed-item shape', function (): void {
    $item = httpClientFeedItem();
    $item->line = 7;

    expect($item->toArray())->toBe([
        'kind' => 'http_client',
        'method' => 'GET',
        'url' => 'https://example.test/users',
        'status' => 200,
        'duration_str' => '42.50ms',
        'duration_ms' => 42.5,
        'request_headers' => ['Accept' => ['application/json']],
        'response_headers' => ['Content-Type' => ['application/json']],
        'body_preview' => '{"id":1}',
        'truncated' => false,
        'content_type' => 'application/json',
        'size' => null,
        'line' => 7,
    ]);
});

it('redacts sensitive request and response headers case-insensitively, keeping the multi-value shape', function (): void {
    $item = httpClientFeedItem([
        'requestHeaders' => [
            'Authorization' => ['Bearer secret-token'],
            'Accept' => ['application/json'],
        ],
        'responseHeaders' => [
            'Set-Cookie' => ['session=abc', 'other=def'],
            'X-Request-Id' => ['req-1'],
        ],
    ]);

    $array = $item->toArray();

    expect($array['request_headers'])->toBe([
        'Authorization' => ['[REDACTED]'],
        'Accept' => ['application/json'],
    ])->and($array['response_headers'])->toBe([
        'Set-Cookie' => ['[REDACTED]', '[REDACTED]'],
        'X-Request-Id' => ['req-1'],
    ]);
});

it('redacts every header on the deny list', function (string $header): void {
    $item = httpClientFeedItem([
        'requestHeaders' => [$header => ['secret-value']],
    ]);

    expect($item->toArray()['request_headers'])->toBe([$header => ['[REDACTED]']]);
})->with([
    'authorization',
    'cookie',
    'set-cookie',
    'x-csrf-token',
    'x-xsrf-token',
    'proxy-authorization',
]);

it('truncates a textual body over the shared max-text-length and flags it', function (): void {
    $body = str_repeat('a', 20_001);

    $item = httpClientFeedItem(['body' => $body, 'contentType' => 'text/plain']);

    $array = $item->toArray();

    expect($array['truncated'])->toBeTrue()
        ->and(mb_strwidth((string) $array['body_preview'], 'UTF-8'))->toBe(20_003) // 20_000 chars + '...'
        ->and($array['body_preview'])->toEndWith('...');
});

it('does not truncate a textual body within the shared max-text-length', function (): void {
    $body = str_repeat('a', 20_000);

    $item = httpClientFeedItem(['body' => $body, 'contentType' => 'text/plain']);

    $array = $item->toArray();

    expect($array['truncated'])->toBeFalse()
        ->and($array['body_preview'])->toBe($body);
});

it('hides the body preview for a non-textual content type and reports content_type and size instead', function (): void {
    $item = httpClientFeedItem(['body' => 'binary-data', 'contentType' => 'application/octet-stream']);

    expect($item->toArray())->toMatchArray([
        'body_preview' => null,
        'truncated' => false,
        'content_type' => 'application/octet-stream',
        'size' => mb_strlen('binary-data'),
    ]);
});

it('treats a missing content type as textual', function (): void {
    $item = httpClientFeedItem(['body' => 'plain text', 'contentType' => null]);

    expect($item->toArray())->toMatchArray([
        'body_preview' => 'plain text',
        'content_type' => null,
        'size' => null,
    ]);
});

it('treats application/xml, application/xml with parameters, and vendor +json/+xml content types as textual', function (string $contentType): void {
    $item = httpClientFeedItem(['body' => '<a/>', 'contentType' => $contentType]);

    expect($item->toArray()['body_preview'])->toBe('<a/>');
})->with([
    'application/xml',
    'application/vnd.api+json',
    'application/xml; charset=utf-8',
    'application/rss+xml',
]);
