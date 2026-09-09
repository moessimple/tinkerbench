<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSnippetContentRequest;
use App\Support\SnippetRepository;
use Illuminate\Http\Response;

class UpdateSnippetContentController
{
    public function __invoke(UpdateSnippetContentRequest $request, SnippetRepository $snippets, string $project, string $snippet): Response
    {
        // A debounced autosave can still be in flight when the snippet is renamed or deleted from the
        // command palette; without this guard that late write would recreate the file under its old
        // name. Updating content never creates a snippet, so a missing one is a 404, not a new file.
        abort_unless($snippets->exists($project, $snippet), Response::HTTP_NOT_FOUND, 'Snippet not found');

        abort_unless($snippets->write($project, $snippet, $request->content()), Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to save the snippet.');

        return response()->noContent();
    }
}
