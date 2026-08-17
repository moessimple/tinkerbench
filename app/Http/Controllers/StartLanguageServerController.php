<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\StartLanguageServerAction;
use Illuminate\Http\JsonResponse;

class StartLanguageServerController
{
    public function __invoke(StartLanguageServerAction $action, string $project): JsonResponse
    {
        return response()->json(['port' => $action->execute($project)]);
    }
}
