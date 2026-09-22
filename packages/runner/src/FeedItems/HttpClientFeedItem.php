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
        public int $status,
        public float $durationMs,
        public array $requestHeaders,
        public array $responseHeaders,
        public string $body,
        public ?string $contentType,
    ) {}

    public function toArray(): array
    {
        $bodyPreview = null;
        $truncated = false;
        $size = null;

        if ($this->isTextual()) {
            $truncated = mb_strwidth($this->body, 'UTF-8') > ValueRenderer::MAX_TEXT_LENGTH;
            $bodyPreview = $truncated
                ? mb_strimwidth($this->body, 0, ValueRenderer::MAX_TEXT_LENGTH, '', 'UTF-8').'...'
                : $this->body;
        } else {
            $size = mb_strlen($this->body);
        }

        return [
            'kind' => FeedItemKind::HttpClient->value,
            'method' => $this->method,
            'url' => $this->url,
            'status' => $this->status,
            'duration_str' => Duration::format($this->durationMs),
            'duration_ms' => $this->durationMs,
            'request_headers' => $this->redact($this->requestHeaders),
            'response_headers' => $this->redact($this->responseHeaders),
            'body_preview' => $bodyPreview,
            'truncated' => $truncated,
            'content_type' => $this->contentType,
            'size' => $size,
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
     * No content type is treated as textual: it is the common case for a quick local test call
     * (e.g. Http::fake() without an explicit header), and hiding the preview by default would lose
     * useful debug output for no proven reason.
     */
    private function isTextual(): bool
    {
        if ($this->contentType === null) {
            return true;
        }

        // No trim(): Pint's mb_str_functions rule would rewrite it to mb_trim(), which is PHP
        // 8.4+ and this package's floor is 8.2 (see ValueRenderer::limit()'s same constraint).
        $type = mb_strtolower(preg_replace('/\s+$/', '', explode(';', $this->contentType)[0]) ?? '');

        return str_starts_with($type, 'text/')
            || $type === 'application/json'
            || $type === 'application/xml'
            || str_ends_with($type, '+json')
            || str_ends_with($type, '+xml');
    }
}
