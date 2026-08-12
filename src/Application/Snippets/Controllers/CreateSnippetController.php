<?php

declare(strict_types=1);

namespace Application\Snippets\Controllers;

use Application\Snippets\Requests\SnippetNameRequest;
use Illuminate\Http\JsonResponse;
use Support\SnippetRepository;

class CreateSnippetController
{
    public function __construct(private SnippetRepository $snippets) {}

    public function __invoke(SnippetNameRequest $request): JsonResponse
    {
        $this->snippets->ensureExists($request->name());

        return response()->json(['ok' => true]);
    }
}
