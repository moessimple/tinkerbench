<?php

declare(strict_types=1);

namespace Application\Snippets\Controllers;

use Application\Snippets\Requests\SnippetNameRequest;
use Illuminate\Http\JsonResponse;
use Support\SnippetRepository;

class CreateSnippetController
{
    public function __invoke(SnippetNameRequest $request, SnippetRepository $snippets): JsonResponse
    {
        $snippets->ensureExists($request->name());

        return response()->json(['ok' => true]);
    }
}
