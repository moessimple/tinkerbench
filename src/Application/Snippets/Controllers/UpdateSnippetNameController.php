<?php

declare(strict_types=1);

namespace Application\Snippets\Controllers;

use Application\Snippets\Requests\SnippetNameRequest;
use Illuminate\Http\JsonResponse;
use Support\Enums\RenameSnippetResult;
use Support\SnippetRepository;

class UpdateSnippetNameController
{
    public function __construct(private SnippetRepository $snippets) {}

    public function __invoke(SnippetNameRequest $request, string $snippet): JsonResponse
    {
        $newName = $request->name();

        $result = $this->snippets->rename($snippet, $newName);

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
