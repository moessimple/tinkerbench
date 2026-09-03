<?php

declare(strict_types=1);

namespace App\Support\Watchers;

use App\Support\SourceLocator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;

class LogWatcher implements Watcher
{
    public function __construct(private SourceLocator $source) {}

    /**
     * @param  callable(array{kind: 'log', label: string, message: string, context: string|null, line: int|null}): void  $emit
     */
    public function register(Application $app, callable $emit): void
    {
        $app->make(Dispatcher::class)->listen(MessageLogged::class, function (MessageLogged $event) use ($emit): void {
            $emit([
                'kind' => 'log',
                'label' => $event->level,
                'message' => $event->message,
                'context' => $this->encodeContext($event->context),
                'line' => $this->source->snippetLine(),
            ]);
        });
    }

    /**
     * @param  array<array-key, mixed>  $context
     */
    private function encodeContext(array $context): ?string
    {
        if ($context === []) {
            return null;
        }

        $encoded = json_encode($context);

        return $encoded === false ? null : $encoded;
    }
}
