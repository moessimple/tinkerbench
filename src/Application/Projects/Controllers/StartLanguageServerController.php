<?php

declare(strict_types=1);

namespace Application\Projects\Controllers;

use Domain\Projects\Actions\StartLanguageServerAction;
use Illuminate\Http\JsonResponse;

class StartLanguageServerController
{
    public function __invoke(StartLanguageServerAction $action, string $project): JsonResponse
    {
        return response()->json(['port' => $action->execute($project)]);
    }
}
