<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Herd;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKnownProjectMiddleware
{
    public function __construct(private Herd $herd) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        abort_if($this->herd->projectPath($project) === null, Response::HTTP_NOT_FOUND, "Unknown Herd project: {$project}");

        return $next($request);
    }
}
