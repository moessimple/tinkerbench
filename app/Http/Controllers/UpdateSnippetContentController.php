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
        $snippets->write($project, $snippet, $request->content());

        return response()->noContent();
    }
}
