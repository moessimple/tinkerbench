<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RunSnippetAction;
use App\Http\Requests\RunSnippetRequest;
use Illuminate\Http\JsonResponse;

class RunSnippetController
{
    public function __invoke(RunSnippetRequest $request, RunSnippetAction $action, string $project): JsonResponse
    {
        $result = $action->execute($project, $request->code());

        return response()->json(['output' => $result->output, 'debug' => $result->debug]);
    }
}
