<?php

declare(strict_types=1);

namespace Application\Snippets\Controllers;

use Application\Snippets\Requests\RunSnippetRequest;
use Domain\Snippets\Actions\RunSnippetAction;
use Illuminate\Http\JsonResponse;

final readonly class RunSnippetController
{
    public function __construct(private RunSnippetAction $runSnippet) {}

    public function __invoke(RunSnippetRequest $request): JsonResponse
    {
        $result = $this->runSnippet->execute($request->code());

        return response()->json(['output' => $result->output]);
    }
}
