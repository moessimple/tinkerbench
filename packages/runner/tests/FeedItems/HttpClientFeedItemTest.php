<?php

declare(strict_types=1);

use Tinkerbench\Runner\FeedItems\HttpClientFeedItem;

function httpClientFeedItem(array $overrides = []): HttpClientFeedItem
{
    $defaults = [
        'method' => 'GET',
        'url' => 'https://example.test/users',
        'faked' => false,
        'status' => 200,
        'startedAt' => 0.0,
        'durationMs' => 42.5,
        'requestHeaders' => ['Accept' => ['application/json']],
        'responseHeaders' => ['Content-Type' => ['application/json']],
        'requestBody' => '',
        'requestSize' => null,
        'requestContentType' => null,
        'responseBody' => '{"id":1}',
        'responseSize' => null,
        'responseContentType' => 'application/json',
    ];

    $args = [...$defaults, ...$overrides];

    return new HttpClientFeedItem(...$args);
}

it('serializes to the http_client feed-item shape', function (): void {
    $item = httpClientFeedItem([
        'requestBody' => '{"name":"Ada"}',
        'requestContentType' => 'application/json',
    ]);
    $item->line = 7;

    expect($item->toArray())->toBe([
        'kind' => 'http_client',
        'method' => 'GET',
        'url' => 'https://example.test/users',
        'request_type' => 'Json',
        'faked' => false,
        'status' => 200,
        'duration_str' => '42.50ms',
        'duration_ms' => 42.5,
        'request_headers' => ['Accept' => ['application/json']],
        'response_headers' => ['Content-Type' => ['application/json']],
        'request_body_preview' => '{"name":"Ada"}',
        'request_truncated' => false,
        'request_content_type' => 'application/json',
        'request_size' => null,
        'response_body_preview' => '{"id":1}',
        'response_truncated' => false,
        'response_content_type' => 'application/json',
        'response_size' => null,
        'line' => 7,
    ]);
});

it('passes request and response headers through unchanged, including sensitive ones', function (): void {
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
        'Authorization' => ['Bearer secret-token'],
        'Accept' => ['application/json'],
    ])->and($array['response_headers'])->toBe([
        'Set-Cookie' => ['session=abc', 'other=def'],
        'X-Request-Id' => ['req-1'],
    ]);
});

it('truncates a textual response body over the shared max-text-length and flags it', function (): void {
    $body = str_repeat('a', 20_001);

    $item = httpClientFeedItem(['responseBody' => $body, 'responseContentType' => 'text/plain']);

    $array = $item->toArray();

    expect($array['response_truncated'])->toBeTrue()
        ->and(mb_strwidth((string) $array['response_body_preview'], 'UTF-8'))->toBe(20_003) // 20_000 chars + '...'
        ->and($array['response_body_preview'])->toEndWith('...');
});

it('does not truncate a textual response body within the shared max-text-length', function (): void {
    $body = str_repeat('a', 20_000);

    $item = httpClientFeedItem(['responseBody' => $body, 'responseContentType' => 'text/plain']);

    $array = $item->toArray();

    expect($array['response_truncated'])->toBeFalse()
        ->and($array['response_body_preview'])->toBe($body);
});

it('hides the response body preview for a non-textual content type and reports content_type and size instead', function (): void {
    $item = httpClientFeedItem(['responseBody' => 'binary-data', 'responseContentType' => 'application/octet-stream']);

    expect($item->toArray())->toMatchArray([
        'response_body_preview' => null,
        'response_truncated' => false,
        'response_content_type' => 'application/octet-stream',
        'response_size' => 11,
    ]);
});

it('reports the size of a hidden body in bytes, not characters', function (): void {
    $item = httpClientFeedItem(['responseBody' => 'äöü', 'responseContentType' => 'application/octet-stream']);

    expect($item->toArray()['response_size'])->toBe(6);
});

it('reports the size of the whole body when only its start was read', function (): void {
    $item = httpClientFeedItem(['responseBody' => 'abc', 'responseSize' => 1_000, 'responseContentType' => 'application/octet-stream']);

    expect($item->toArray()['response_size'])->toBe(1_000);
});

it('flags a textual body as truncated when only its start was read', function (): void {
    $item = httpClientFeedItem(['responseBody' => 'abc', 'responseSize' => 1_000, 'responseContentType' => 'text/plain']);

    expect($item->toArray())->toMatchArray([
        'response_body_preview' => 'abc...',
        'response_truncated' => true,
    ]);
});

it('treats a missing content type as textual', function (): void {
    $item = httpClientFeedItem(['responseBody' => 'plain text', 'responseContentType' => null]);

    expect($item->toArray())->toMatchArray([
        'response_body_preview' => 'plain text',
        'response_content_type' => null,
        'response_size' => null,
    ]);
});

it('treats application/xml, application/xml with parameters, and vendor +json/+xml content types as textual', function (string $contentType): void {
    $item = httpClientFeedItem(['responseBody' => '<a/>', 'responseContentType' => $contentType]);

    expect($item->toArray()['response_body_preview'])->toBe('<a/>');
})->with([
    'application/xml',
    'application/vnd.api+json',
    'application/xml; charset=utf-8',
    'application/rss+xml',
]);

it('truncates and hides the request body just like the response body', function (): void {
    $overLimit = str_repeat('a', 20_001);

    $item = httpClientFeedItem(['requestBody' => $overLimit, 'requestContentType' => 'text/plain']);
    $textual = $item->toArray();

    expect($textual['request_truncated'])->toBeTrue()
        ->and($textual['request_body_preview'])->toEndWith('...');

    $item = httpClientFeedItem(['requestBody' => 'binary', 'requestContentType' => 'application/octet-stream']);
    $binary = $item->toArray();

    expect($binary['request_body_preview'])->toBeNull()
        ->and($binary['request_size'])->toBe(6);
});

it('classifies the request type from its content type', function (?string $contentType, string $expected): void {
    $item = httpClientFeedItem(['requestContentType' => $contentType]);

    expect($item->toArray()['request_type'])->toBe($expected);
})->with([
    'json content type' => ['application/json', 'Json'],
    'vendor json content type' => ['application/vnd.api+json', 'Json'],
    'multipart content type' => ['multipart/form-data; boundary=x', 'Multipart'],
    'form content type' => ['application/x-www-form-urlencoded', 'Form'],
    'plain text content type' => ['text/plain', 'Other'],
    'no content type' => [null, 'Other'],
]);

it('reports whether the request was faked', function (): void {
    expect(httpClientFeedItem(['faked' => true])->toArray()['faked'])->toBeTrue()
        ->and(httpClientFeedItem(['faked' => false])->toArray()['faked'])->toBeFalse();
});
