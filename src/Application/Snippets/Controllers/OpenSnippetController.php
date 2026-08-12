<?php

declare(strict_types=1);

namespace Application\Snippets\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Support\SnippetRepository;

class OpenSnippetController
{
    public function __construct(private SnippetRepository $snippets) {}

    public function __invoke(?string $snippet = null): Response
    {
        $snippetName = $snippet ?? 'scratch';

        $this->snippets->ensureExists($snippetName);

        return Inertia::render('Snippets/Run', [
            'snippetName' => $snippetName,
            'content' => $this->snippets->contents($snippetName),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
        ]);
    }
}
