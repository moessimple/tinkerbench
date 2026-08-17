<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SnippetRepository;
use Illuminate\Http\JsonResponse;

class DeleteSnippetController
{
    public function __invoke(SnippetRepository $snippets, string $project, string $snippet): JsonResponse
    {
        if (! $snippets->delete($project, $snippet)) {
            return response()->json([
                'ok' => false,
                'error' => 'Snippet not found',
            ], 404);
        }

        return response()->json(['ok' => true]);
    }
}
