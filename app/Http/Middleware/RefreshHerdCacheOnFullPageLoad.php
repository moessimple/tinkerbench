<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Herd;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshHerdCacheOnFullPageLoad
{
    public function __construct(private Herd $herd) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->has('X-Inertia')) {
            return $next($request);
        }

        $projects = $this->herd->refreshProjects();
        $routeProject = $request->route('project');
        $project = $this->herd->resolveProject(is_string($routeProject) ? $routeProject : null);

        if (array_key_exists($project, $projects)) {
            $phpBinary = $this->herd->refreshPhpBinary($project);
            $this->herd->refreshPhpVersion($phpBinary);

            $projectPath = $this->herd->projectPath($project);

            if ($projectPath !== null) {
                $this->herd->refreshLaravelVersion($phpBinary, $projectPath);
            }
        }

        return $next($request);
    }
}
