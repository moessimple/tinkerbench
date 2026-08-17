<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SnippetRepository;
use Illuminate\Http\JsonResponse;

class ListSnippetsController
{
    public function __invoke(SnippetRepository $snippets, string $project): JsonResponse
    {
        return response()->json($snippets->names($project));
    }
}
