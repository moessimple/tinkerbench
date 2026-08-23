<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DeleteSnippetResult;
use App\Support\SnippetRepository;
use Illuminate\Http\Response;

class DeleteSnippetController
{
    public function __invoke(SnippetRepository $snippets, string $project, string $snippet): Response
    {
        $result = $snippets->delete($project, $snippet);

        abort_if($result === DeleteSnippetResult::Missing, Response::HTTP_NOT_FOUND, 'Snippet not found');
        abort_if($result === DeleteSnippetResult::Failed, Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to delete the snippet.');

        return response()->noContent();
    }
}
