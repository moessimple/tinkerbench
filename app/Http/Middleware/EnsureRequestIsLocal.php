<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequestIsLocal
{
    private const array LOOPBACK_ADDRESSES = ['127.0.0.1', '::1'];

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        // $request->ip() also correctly resolves a request tunneled in via `herd share`: bootstrap/app.php
        // trusts the local nginx peer for X-Forwarded-For, and Expose sets that header from the real remote
        // connection, discarding whatever a client tries to claim, so a tunneled visitor's real IP is seen
        // here instead of nginx's own loopback connection to php-fpm. See .ai/rules/middleware.md.
        abort_unless(in_array($request->ip(), self::LOOPBACK_ADDRESSES, true), Response::HTTP_FORBIDDEN, 'tinkerbench only accepts requests from this machine.');

        return $next($request);
    }
}
