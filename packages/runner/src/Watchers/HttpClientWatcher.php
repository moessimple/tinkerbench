<?php

declare(strict_types=1);

namespace Tinkerbench\Runner\Watchers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Tinkerbench\Runner\FeedItems\HttpClientFeedItem;

class HttpClientWatcher implements Watcher
{
    /**
     * spl_object_id($event->request) => start time. RequestSending and ResponseReceived carry the
     * same Request instance for one call (PendingRequest fires both with it), so this needs no
     * correlation id of its own. ConnectionFailed is deliberately not listened to: a failed
     * connection throws and reaches the feed through the existing uncaught-exception path instead.
     *
     * @var array<int, float>
     */
    private array $startedAt = [];

    public function register(Application $app, callable $emit): void
    {
        $dispatcher = $app->make(Dispatcher::class);

        $dispatcher->listen(RequestSending::class, function (RequestSending $event): void {
            $this->startedAt[spl_object_id($event->request)] = microtime(true);
        });

        $dispatcher->listen(ResponseReceived::class, function (ResponseReceived $event) use ($emit): void {
            $id = spl_object_id($event->request);

            if (! isset($this->startedAt[$id])) {
                return;
            }

            $durationMs = (microtime(true) - $this->startedAt[$id]) * 1000;
            unset($this->startedAt[$id]);

            // Request/Response only declare these as `array`, but both wrap a PSR-7 message, whose
            // getHeaders() contract guarantees array<string, string[]> (MessageInterface::getHeaders()).
            /** @var array<string, list<string>> $requestHeaders */
            $requestHeaders = $event->request->headers();
            /** @var array<string, list<string>> $responseHeaders */
            $responseHeaders = $event->response->headers();

            // handlerStats() comes from the cURL handler Guzzle actually drove; Http::fake()
            // never touches cURL, so a faked response always reports empty stats here.
            $emit(new HttpClientFeedItem(
                $event->request->method(),
                $event->request->url(),
                empty($event->response->handlerStats()),
                $event->response->status(),
                $durationMs,
                $requestHeaders,
                $responseHeaders,
                $event->request->body(),
                $requestHeaders['Content-Type'][0] ?? null,
                $event->response->body(),
                $event->response->header('Content-Type') ?: null,
            ));
        });
    }
}
