<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CreateSnippetResult;
use App\Http\Requests\SnippetNameRequest;
use App\Support\SnippetRepository;
use Illuminate\Http\Response;

class CreateSnippetController
{
    public function __invoke(SnippetNameRequest $request, SnippetRepository $snippets, string $project): Response
    {
        $name = $request->name();

        $result = $snippets->create($project, $name);

        abort_if($result === CreateSnippetResult::Conflict, Response::HTTP_CONFLICT, "A snippet named '{$name}' already exists");
        abort_if($result === CreateSnippetResult::Failed, Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to create the snippet.');

        return response()->noContent();
    }
}
