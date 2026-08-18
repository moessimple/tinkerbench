<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SnippetNameRequest;
use App\Support\SnippetRepository;
use Illuminate\Http\Response;

class CreateSnippetController
{
    public function __invoke(SnippetNameRequest $request, SnippetRepository $snippets, string $project): Response
    {
        $snippets->ensureExists($project, $request->name());

        return response()->noContent();
    }
}
