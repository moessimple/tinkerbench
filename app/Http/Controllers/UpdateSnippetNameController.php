<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RenameSnippetResult;
use App\Http\Requests\SnippetNameRequest;
use App\Support\SnippetRepository;
use Illuminate\Http\Response;

class UpdateSnippetNameController
{
    public function __invoke(SnippetNameRequest $request, SnippetRepository $snippets, string $project, string $snippet): Response
    {
        $newName = $request->name();

        $result = $snippets->rename($project, $snippet, $newName);

        abort_if($result === RenameSnippetResult::Missing, Response::HTTP_NOT_FOUND, 'Snippet not found');
        abort_if($result === RenameSnippetResult::Conflict, Response::HTTP_CONFLICT, "A snippet named '{$newName}' already exists");
        abort_if($result === RenameSnippetResult::Failed, Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to rename the snippet.');

        return response()->noContent();
    }
}
