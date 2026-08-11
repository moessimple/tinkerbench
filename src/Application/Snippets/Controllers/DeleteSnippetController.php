<?php

declare(strict_types=1);

namespace Application\Snippets\Controllers;

use Illuminate\Http\JsonResponse;
use Support\SnippetRepository;

class DeleteSnippetController
{
    public function __construct(private SnippetRepository $snippets) {}

    public function __invoke(string $snippet): JsonResponse
    {
        if (! $this->snippets->delete($snippet)) {
            return response()->json([
                'ok' => false,
                'error' => 'Snippet not found',
            ], 404);
        }

        return response()->json(['ok' => true]);
    }
}
