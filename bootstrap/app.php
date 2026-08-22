<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureRequestIsLocal;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Process\Exceptions\ProcessFailedException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Herd's nginx only ever connects to php-fpm from 127.0.0.1, even for a request tunneled in via
        // `herd share`: Expose terminates the real remote connection itself and forwards it locally. Trusting
        // that peer for X-Forwarded-For lets EnsureRequestIsLocal see the tunneled visitor's real IP instead
        // of always reading 127.0.0.1. Expose's own edge sets this header from the real connection and
        // discards whatever a client tries to claim, so it can't be spoofed by the visitor.
        $middleware->trustProxies(at: ['127.0.0.1', '::1'], headers: Request::HEADER_X_FORWARDED_FOR);

        $middleware->prepend(EnsureRequestIsLocal::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ProcessFailedException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unable to reach Herd. Make sure Herd is running and try again.'], 500);
            }
        });
    })->create();
