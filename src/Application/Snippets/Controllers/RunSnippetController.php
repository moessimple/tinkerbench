<?php

declare(strict_types=1);

namespace Application\Snippets\Controllers;

use Application\Snippets\Requests\RunSnippetRequest;
use Domain\Snippets\Actions\RunSnippetAction;
use Illuminate\Http\JsonResponse;

class RunSnippetController
{
    public function __invoke(RunSnippetRequest $request, RunSnippetAction $action, ?string $project = null): JsonResponse
    {
        $result = $action->execute($request->code(), $project);

        return response()->json(['output' => $result->output]);
    }
}
