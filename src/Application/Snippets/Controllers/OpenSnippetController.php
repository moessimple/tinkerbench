<?php

declare(strict_types=1);

namespace Application\Snippets\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Support\Herd;
use Support\SnippetRepository;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OpenSnippetController
{
    public function __invoke(SnippetRepository $snippets, Herd $herd, ?string $project = null, ?string $snippet = null): Response
    {
        $project ??= $herd->currentProject();
        $projectPath = $herd->projectPath($project);

        abort_if($projectPath === null, HttpResponse::HTTP_NOT_FOUND, "Unknown Herd project: {$project}");

        $snippetName = $snippet ?? 'scratch';

        $snippets->ensureExists($project, $snippetName);

        $phpBinary = $herd->phpBinary($project);

        return Inertia::render('Snippets/OpenSnippet', [
            'snippetName' => $snippetName,
            'content' => $snippets->contents($project, $snippetName),
            'currentProject' => $project,
            'phpVersion' => $herd->phpVersion($phpBinary),
            'laravelVersion' => $herd->laravelVersion($phpBinary, $projectPath),
        ]);
    }
}
