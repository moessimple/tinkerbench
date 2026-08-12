<?php

declare(strict_types=1);

namespace Application\Snippets\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Support\Herd;
use Support\SnippetRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OpenSnippetController
{
    public function __invoke(SnippetRepository $snippets, Herd $herd, ?string $project = null, ?string $snippet = null): Response
    {
        // A bare single-segment URL used to mean "open this snippet" before project switching
        // existed, so an old bookmark like /scratch would otherwise now be parsed as an unknown
        // project. If the single segment isn't a known project, it's a snippet name instead.
        if ($snippet === null && $project !== null && $herd->projectPath($project) === null) {
            [$project, $snippet] = [null, $project];
        }

        $project ??= $herd->currentProject();
        $projectPath = $herd->projectPath($project);

        throw_if($projectPath === null, NotFoundHttpException::class, "Unknown Herd project: {$project}");

        $snippetName = $snippet ?? 'scratch';

        $snippets->ensureExists($project, $snippetName);

        $phpBinary = $herd->phpBinary($project);

        return Inertia::render('Snippets/Run', [
            'snippetName' => $snippetName,
            'content' => $snippets->contents($project, $snippetName),
            'currentProject' => $project,
            'phpVersion' => $herd->phpVersion($phpBinary),
            'laravelVersion' => $herd->laravelVersion($phpBinary, $projectPath),
        ]);
    }
}
