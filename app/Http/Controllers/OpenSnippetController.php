<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Herd;
use App\Support\ProjectSnapshot;
use App\Support\SnippetRepository;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class OpenSnippetController
{
    public function __invoke(SnippetRepository $snippets, Herd $herd, ?string $project = null, ?string $snippet = null): Response
    {
        $snapshot = $herd->snapshotProject($project);

        abort_if(! $snapshot instanceof ProjectSnapshot, HttpResponse::HTTP_NOT_FOUND, "Unknown Herd project: {$project}");

        $snippetName = $snippet ?? 'scratch';

        abort_unless($snippets->ensureExists($snapshot->name, $snippetName), HttpResponse::HTTP_INTERNAL_SERVER_ERROR, 'Unable to create the snippet.');

        return Inertia::render('Snippets/OpenSnippet', [
            'snippetName' => $snippetName,
            'content' => $snippets->contents($snapshot->name, $snippetName),
            'currentProject' => $snapshot->name,
            'phpVersion' => $snapshot->phpVersion,
            'laravelVersion' => $snapshot->laravelVersion,
        ]);
    }
}
