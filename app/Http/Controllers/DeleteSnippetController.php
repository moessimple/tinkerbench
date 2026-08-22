<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SnippetRepository;
use Illuminate\Http\Response;

class DeleteSnippetController
{
    public function __invoke(SnippetRepository $snippets, string $project, string $snippet): Response
    {
        abort_unless($snippets->delete($project, $snippet), Response::HTTP_NOT_FOUND, 'Snippet not found');

        return response()->noContent();
    }
}
