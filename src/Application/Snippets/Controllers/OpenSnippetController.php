<?php

declare(strict_types=1);

namespace Application\Snippets\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Support\SnippetRepository;

class OpenSnippetController
{
    public function __invoke(SnippetRepository $snippets, ?string $snippet = null): Response
    {
        $snippetName = $snippet ?? 'scratch';

        $snippets->ensureExists($snippetName);

        return Inertia::render('Snippets/Run', [
            'snippetName' => $snippetName,
            'content' => $snippets->contents($snippetName),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
        ]);
    }
}
