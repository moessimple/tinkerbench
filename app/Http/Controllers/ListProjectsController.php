<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Herd;
use Illuminate\Http\JsonResponse;

class ListProjectsController
{
    public function __invoke(Herd $herd): JsonResponse
    {
        return response()->json($herd->projectNames());
    }
}
