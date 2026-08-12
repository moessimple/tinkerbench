<?php

declare(strict_types=1);

namespace Application\Snippets\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Support\Herd;
use Support\SnippetRepository;

class OpenSnippetController
{
    public function __invoke(SnippetRepository $snippets, Herd $herd, ?string $project = null, ?string $snippet = null): Response
    {
        $project ??= $herd->currentProject();
        $projectPath = $herd->projectPath($project);

        throw_if($projectPath === null, RuntimeException::class, "Unknown Herd project: {$project}");

        $snippetName = $snippet ?? 'scratch';

        $snippets->ensureExists($project, $snippetName);

        $phpBinary = $herd->phpBinary($project);

        return Inertia::render('Snippets/Run', [
            'snippetName' => $snippetName,
            'content' => $snippets->contents($project, $snippetName),
            'currentProject' => $project,
            'projects' => array_keys($herd->projects()),
            'phpVersion' => $herd->phpVersion($phpBinary),
            'laravelVersion' => $herd->laravelVersion($phpBinary, $projectPath),
        ]);
    }
}
