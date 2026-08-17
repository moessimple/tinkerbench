<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RenameSnippetResult;
use App\Http\Requests\SnippetNameRequest;
use App\Support\SnippetRepository;
use Illuminate\Http\JsonResponse;

class UpdateSnippetNameController
{
    public function __invoke(SnippetNameRequest $request, SnippetRepository $snippets, string $project, string $snippet): JsonResponse
    {
        $newName = $request->name();

        $result = $snippets->rename($project, $snippet, $newName);

        if ($result === RenameSnippetResult::Missing) {
            return response()->json([
                'ok' => false,
                'error' => 'Snippet not found',
            ], 404);
        }

        if ($result === RenameSnippetResult::Conflict) {
            return response()->json([
                'ok' => false,
                'error' => "A snippet named \"{$newName}\" already exists",
            ], 409);
        }

        return response()->json(['ok' => true]);
    }
}
