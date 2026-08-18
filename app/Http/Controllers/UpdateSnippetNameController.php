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

        abort_if($result === RenameSnippetResult::Missing, 404, 'Snippet not found');
        abort_if($result === RenameSnippetResult::Conflict, 409, "A snippet named '{$newName}' already exists");

        return response()->noContent();
    }
}
