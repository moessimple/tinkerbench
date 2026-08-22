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
        abort_unless($snippets->write($project, $snippet, $request->content()), Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to save the snippet.');

        return response()->noContent();
    }
}
