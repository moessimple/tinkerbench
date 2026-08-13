<?php

declare(strict_types=1);

namespace Application\Projects\Middleware;

use Closure;
use Illuminate\Http\Request;
use Support\Herd;
use Symfony\Component\HttpFoundation\Response;

class RefreshHerdCacheOnFullPageLoadMiddleware
{
    public function __construct(private Herd $herd) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->has('X-Inertia')) {
            return $next($request);
        }

        $projects = $this->herd->refreshProjects();
        $project = $request->route('project');
        $project = is_string($project) ? $project : $this->herd->currentProject();

        if (array_key_exists($project, $projects)) {
            $this->herd->refreshPhpBinary($project);
        }

        return $next($request);
    }
}
