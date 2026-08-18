<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SnippetRepository;
use Illuminate\Http\Response;

class DeleteSnippetController
{
    public function __invoke(SnippetRepository $snippets, string $project, string $snippet): Response
    {
        abort_unless($snippets->delete($project, $snippet), 404, 'Snippet not found');

        return response()->noContent();
    }
}
