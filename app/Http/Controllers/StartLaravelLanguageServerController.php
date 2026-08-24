<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\StartLaravelLanguageServerAction;
use Illuminate\Http\JsonResponse;

class StartLaravelLanguageServerController
{
    public function __invoke(StartLaravelLanguageServerAction $action, string $project): JsonResponse
    {
        return response()->json(['port' => $action->execute($project)]);
    }
}
