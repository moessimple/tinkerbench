<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SnippetNameRequest;
use App\Support\SnippetRepository;
use Illuminate\Http\JsonResponse;

class CreateSnippetController
{
    public function __invoke(SnippetNameRequest $request, SnippetRepository $snippets, string $project): JsonResponse
    {
        $snippets->ensureExists($project, $request->name());

        return response()->json(['ok' => true]);
    }
}
