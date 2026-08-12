<?php

declare(strict_types=1);

namespace Application\Snippets\Controllers;

use Illuminate\Http\JsonResponse;
use Support\SnippetRepository;

class ListSnippetsController
{
    public function __invoke(SnippetRepository $snippets, string $project): JsonResponse
    {
        return response()->json($snippets->names($project));
    }
}
