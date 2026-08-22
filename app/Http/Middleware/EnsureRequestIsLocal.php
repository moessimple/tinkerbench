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
        abort_unless(in_array($request->ip(), self::LOOPBACK_ADDRESSES, true), Response::HTTP_FORBIDDEN, 'tinkerbench only accepts requests from this machine.');

        return $next($request);
    }
}
