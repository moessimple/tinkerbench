<?php

declare(strict_types=1);

namespace Tinkerbench\Runner\FeedItems;

use Tinkerbench\Runner\Duration;
use Tinkerbench\Runner\FeedItemKind;
use Tinkerbench\Runner\ValueRenderer;

class HttpClientFeedItem extends FeedItem
{
    /**
     * Header names (case-insensitive) whose values never reach the feed: the feed is a browser
     * page with a copy button, unlike a native desktop app, so secrets pasted from it are a real
     * leak vector even for a single-developer local tool.
     *
     * @var list<string>
     */
    private const REDACTED_HEADERS = [
        'authorization',
        'cookie',
        'set-cookie',
        'x-csrf-token',
        'x-xsrf-token',
        'proxy-authorization',
    ];

    /**
     * @param  array<string, list<string>>  $requestHeaders
     * @param  array<string, list<string>>  $responseHeaders
     */
    public function __construct(
        public string $method,
        public string $url,
        public bool $faked,
        public int $status,
        public float $durationMs,
        public array $requestHeaders,
        public array $responseHeaders,
        public string $requestBody,
        public ?string $requestContentType,
        public string $responseBody,
        public ?string $responseContentType,
    ) {}

    public function toArray(): array
    {
        $request = $this->preview($this->requestBody, $this->requestContentType);
        $response = $this->preview($this->responseBody, $this->responseContentType);

        return [
            'kind' => FeedItemKind::HttpClient->value,
            'method' => $this->method,
            'url' => $this->url,
            'request_type' => $this->requestType(),
            'faked' => $this->faked,
            'status' => $this->status,
            'duration_str' => Duration::format($this->durationMs),
            'duration_ms' => $this->durationMs,
            'request_headers' => $this->redact($this->requestHeaders),
            'response_headers' => $this->redact($this->responseHeaders),
            'request_body_preview' => $request['preview'],
            'request_truncated' => $request['truncated'],
            'request_content_type' => $this->requestContentType,
            'request_size' => $request['size'],
            'response_body_preview' => $response['preview'],
            'response_truncated' => $response['truncated'],
            'response_content_type' => $this->responseContentType,
            'response_size' => $response['size'],
            'line' => $this->line,
        ];
    }

    /**
     * @param  array<string, list<string>>  $headers
     * @return array<string, list<string>>
     */
    private function redact(array $headers): array
    {
        $redacted = [];

        foreach ($headers as $name => $values) {
            $redacted[$name] = in_array(mb_strtolower($name), self::REDACTED_HEADERS, true)
                ? array_fill(0, count($values), '[REDACTED]')
                : $values;
        }

        return $redacted;
    }

    /**
     * @return array{preview: string|null, truncated: bool, size: int|null}
     */
    private function preview(string $body, ?string $contentType): array
    {
        if (! $this->isTextual($contentType)) {
            return ['preview' => null, 'truncated' => false, 'size' => mb_strlen($body)];
        }

        $truncated = mb_strwidth($body, 'UTF-8') > ValueRenderer::MAX_TEXT_LENGTH;

        return [
            'preview' => $truncated
                ? mb_strimwidth($body, 0, ValueRenderer::MAX_TEXT_LENGTH, '', 'UTF-8').'...'
                : $body,
            'truncated' => $truncated,
            'size' => null,
        ];
    }

    /**
     * No content type is treated as textual: it is the common case for a quick local test call
     * (e.g. Http::fake() without an explicit header), and hiding the preview by default would lose
     * useful debug output for no proven reason.
     */
    private function isTextual(?string $contentType): bool
    {
        if ($contentType === null) {
            return true;
        }

        $type = $this->baseType($contentType);

        return str_starts_with($type, 'text/')
            || $type === 'application/json'
            || $type === 'application/xml'
            || str_ends_with($type, '+json')
            || str_ends_with($type, '+xml');
    }

    /**
     * A request with a raw body and no recognized content type is neither JSON, multipart, nor
     * form data, so it falls back to Other rather than being mislabelled as one of those.
     */
    private function requestType(): string
    {
        if ($this->requestContentType === null) {
            return 'Other';
        }

        $type = $this->baseType($this->requestContentType);

        if (str_contains($type, 'json')) {
            return 'Json';
        }

        if (str_contains($type, 'multipart')) {
            return 'Multipart';
        }

        if ($type === 'application/x-www-form-urlencoded') {
            return 'Form';
        }

        return 'Other';
    }

    private function baseType(string $contentType): string
    {
        // No trim(): Pint's mb_str_functions rule would rewrite it to mb_trim(), which is PHP
        // 8.4+ and this package's floor is 8.2 (see ValueRenderer::limit()'s same constraint).
        return mb_strtolower(preg_replace('/\s+$/', '', explode(';', $contentType)[0]) ?? '');
    }
}
