<?php

declare(strict_types=1);

namespace Application\Projects\Controllers;

use Illuminate\Http\JsonResponse;
use Support\Herd;

class ListProjectsController
{
    public function __invoke(Herd $herd): JsonResponse
    {
        return response()->json($herd->projectNames());
    }
}
