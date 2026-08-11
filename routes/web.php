<?php

declare(strict_types=1);

use Application\Snippets\Controllers\ListSnippetsController;
use Application\Snippets\Controllers\OpenSnippetController;
use Application\Snippets\Controllers\RunSnippetController;
use Application\Snippets\Controllers\UpdateSnippetContentController;
use Illuminate\Support\Facades\Route;

Route::pattern('snippet', '[A-Za-z0-9_-]+');

Route::post('snippets/executions', RunSnippetController::class);

Route::prefix('api/snippets')->group(function (): void {
    Route::get('', ListSnippetsController::class);
    Route::put('{snippet}', UpdateSnippetContentController::class);
});

Route::get('{snippet?}', OpenSnippetController::class);
