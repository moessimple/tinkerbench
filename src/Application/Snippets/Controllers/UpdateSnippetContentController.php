<?php

declare(strict_types=1);

namespace Application\Snippets\Controllers;

use Application\Snippets\Requests\UpdateSnippetContentRequest;
use Illuminate\Http\JsonResponse;
use Support\SnippetRepository;

class UpdateSnippetContentController
{
    public function __invoke(UpdateSnippetContentRequest $request, SnippetRepository $snippets, string $snippet): JsonResponse
    {
        $snippets->write($snippet, $request->content());

        return response()->json(['ok' => true]);
    }
}
