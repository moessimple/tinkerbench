<?php

declare(strict_types=1);

namespace Tinkerbench\Runner\FeedItems;

use Tinkerbench\Runner\Duration;
use Tinkerbench\Runner\FeedItemKind;
use Tinkerbench\Runner\ValueRenderer;

class HttpClientFeedItem extends FeedItem
{
    /**
     * @param  float  $startedAt  hrtime(true) when the request was handed to the transport. Not part
     *                            of the wire shape: the recorder uses it with $durationMs to count
     *                            overlapping requests' time once.
     * @param  int|null  $requestSize  Byte size of the whole body when known. The body string can
     *                                 be cut short of it: the watcher reads only what the preview
     *                                 needs. Null means the body string is the whole body.
     * @param  int|null  $responseSize  Same as $requestSize, for the response body.
     * @param  array<string, list<string>>  $requestHeaders
     * @param  array<string, list<string>>  $responseHeaders
     */
    public function __construct(
        public string $method,
        public string $url,
        public bool $faked,
        public int $status,
        public float $startedAt,
        public float $durationMs,
        public array $requestHeaders,
        public array $responseHeaders,
        public string $requestBody,
        public ?int $requestSize,
        public ?string $requestContentType,
        public string $responseBody,
        public ?int $responseSize,
        public ?string $responseContentType,
    ) {}

    public function toArray(): array
    {
        $request = $this->preview($this->requestBody, $this->requestSize, $this->requestContentType);
        $response = $this->preview($this->responseBody, $this->responseSize, $this->responseContentType);

        return [
            'kind' => FeedItemKind::HttpClient->value,
            'method' => $this->method,
            'url' => $this->url,
            'request_type' => $this->requestType(),
            'faked' => $this->faked,
            'status' => $this->status,
            'duration_str' => Duration::format($this->durationMs),
            'duration_ms' => $this->durationMs,
            'request_headers' => $this->requestHeaders,
            'response_headers' => $this->responseHeaders,
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
     * @return array{preview: string|null, truncated: bool, size: int|null}
     */
    private function preview(string $body, ?int $size, ?string $contentType): array
    {
        // '8bit' counts bytes: Pint's mb_str_functions rule would rewrite a plain strlen().
        $readBytes = mb_strlen($body, '8bit');
        $size ??= $readBytes;

        if (! $this->isTextual($contentType)) {
            return ['preview' => null, 'truncated' => false, 'size' => $size];
        }

        $truncated = $readBytes < $size || mb_strwidth($body, 'UTF-8') > ValueRenderer::MAX_TEXT_LENGTH;

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
