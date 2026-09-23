<?php

declare(strict_types=1);

namespace Tinkerbench\Runner\Watchers;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\TransferStats;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Tinkerbench\Runner\FeedItems\HttpClientFeedItem;

class HttpClientWatcher implements Watcher
{
    /**
     * Records every network request, not every Http:: call: the global middleware sits inside
     * Guzzle's redirect middleware, so each hop of a redirect chain passes through it on its own
     * and gets its own item and duration. The RequestSending/ResponseReceived events cannot do
     * this, since ResponseReceived fires once per call and only names the final hop. A connection
     * failure rejects the promise and reaches the feed through the uncaught-exception path.
     */
    public function register(Application $app, callable $emit): void
    {
        $app->make(Factory::class)->globalMiddleware(fn (callable $handler): Closure => $this->recordRequests($handler, $emit));
    }

    /**
     * @param  callable(RequestInterface, array<mixed>): PromiseInterface  $handler
     * @return Closure(RequestInterface, array<mixed>): PromiseInterface
     */
    private function recordRequests(callable $handler, callable $emit): Closure
    {
        return function (RequestInterface $request, array $options) use ($handler, $emit): PromiseInterface {
            // Http::fake() answers from a stub handler that never reports transfer stats, so a
            // request without them was faked.
            $transferStats = null;
            $onStats = $options['on_stats'] ?? null;
            $options['on_stats'] = function (TransferStats $stats) use (&$transferStats, $onStats): void {
                $transferStats = $stats;

                if (is_callable($onStats)) {
                    $onStats($stats);
                }
            };

            $startedAt = hrtime(true);

            return $handler($request, $options)->then(function (ResponseInterface $response) use ($request, $emit, $startedAt, &$transferStats): ResponseInterface {
                // PSR-7's MessageInterface::getHeaders() contract guarantees array<string, string[]>.
                /** @var array<string, list<string>> $requestHeaders */
                $requestHeaders = $request->getHeaders();
                /** @var array<string, list<string>> $responseHeaders */
                $responseHeaders = $response->getHeaders();

                $emit(new HttpClientFeedItem(
                    $request->getMethod(),
                    (string) $request->getUri(),
                    ! $transferStats instanceof TransferStats || $transferStats->getHandlerStats() === [],
                    $response->getStatusCode(),
                    (hrtime(true) - $startedAt) / 1_000_000,
                    $requestHeaders,
                    $responseHeaders,
                    $this->contents($request->getBody()),
                    $request->getHeader('Content-Type')[0] ?? null,
                    $this->contents($response->getBody()),
                    $response->getHeader('Content-Type')[0] ?? null,
                ));

                return $response;
            });
        };
    }

    /**
     * Reads a body without consuming it for the caller: a streamed, non-seekable body is left
     * untouched and recorded as empty, since reading it here would take it from the snippet.
     */
    private function contents(StreamInterface $body): string
    {
        if (! $body->isSeekable()) {
            return '';
        }

        $body->rewind();
        $contents = $body->getContents();
        $body->rewind();

        return $contents;
    }
}
