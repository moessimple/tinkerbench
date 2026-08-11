<?php

declare(strict_types=1);

namespace Application\Snippets\Controllers;

use Illuminate\Http\JsonResponse;
use Support\SnippetRepository;

class ListSnippetsController
{
    public function __construct(private SnippetRepository $snippets) {}

    public function __invoke(): JsonResponse
    {
        return response()->json($this->snippets->names());
    }
}
