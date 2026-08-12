<?php

declare(strict_types=1);

use Application\Snippets\Controllers\CreateSnippetController;
use Application\Snippets\Controllers\DeleteSnippetController;
use Application\Snippets\Controllers\ListSnippetsController;
use Application\Snippets\Controllers\OpenSnippetController;
use Application\Snippets\Controllers\RunSnippetController;
use Application\Snippets\Controllers\UpdateSnippetContentController;
use Application\Snippets\Controllers\UpdateSnippetNameController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;

Route::pattern('snippet', '[A-Za-z0-9_-]+');

Route::post('snippets/executions', RunSnippetController::class);

Route::prefix('api/snippets')->group(function (): void {
    Route::get('', ListSnippetsController::class);
    Route::post('', CreateSnippetController::class)->middleware(HandlePrecognitiveRequests::class);
    Route::put('{snippet}', UpdateSnippetContentController::class);
    Route::patch('{snippet}', UpdateSnippetNameController::class)->middleware(HandlePrecognitiveRequests::class);
    Route::delete('{snippet}', DeleteSnippetController::class);
});

Route::get('{snippet?}', OpenSnippetController::class);
