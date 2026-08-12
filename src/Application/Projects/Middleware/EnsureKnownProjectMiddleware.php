<?php

declare(strict_types=1);

namespace Application\Projects\Middleware;

use Closure;
use Illuminate\Http\Request;
use Support\Herd;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EnsureKnownProjectMiddleware
{
    public function __construct(private Herd $herd) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        throw_if(! is_string($project) || $this->herd->projectPath($project) === null, NotFoundHttpException::class, "Unknown Herd project: {$project}");

        return $next($request);
    }
}
