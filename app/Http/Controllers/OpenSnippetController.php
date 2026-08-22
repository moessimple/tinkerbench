<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Herd;
use App\Support\SnippetRepository;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OpenSnippetController
{
    public function __invoke(SnippetRepository $snippets, Herd $herd, ?string $project = null, ?string $snippet = null): Response
    {
        $project ??= $herd->currentProject();
        $projectPath = $herd->projectPath($project);

        abort_if($projectPath === null, HttpResponse::HTTP_NOT_FOUND, "Unknown Herd project: {$project}");

        $snippetName = $snippet ?? 'scratch';

        abort_unless($snippets->ensureExists($project, $snippetName), HttpResponse::HTTP_INTERNAL_SERVER_ERROR, 'Unable to create the snippet.');

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
