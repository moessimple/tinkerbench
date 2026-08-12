<?php

declare(strict_types=1);

use Application\Projects\Controllers\ListProjectsController;
use Application\Projects\Middleware\EnsureKnownProjectMiddleware;
use Application\Snippets\Controllers\CreateSnippetController;
use Application\Snippets\Controllers\DeleteSnippetController;
use Application\Snippets\Controllers\ListSnippetsController;
use Application\Snippets\Controllers\OpenSnippetController;
use Application\Snippets\Controllers\RunSnippetController;
use Application\Snippets\Controllers\UpdateSnippetContentController;
use Application\Snippets\Controllers\UpdateSnippetNameController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;

Route::pattern('project', '[A-Za-z0-9_-]+');
Route::pattern('snippet', '[A-Za-z0-9_-]+');

Route::post('projects/{project}/snippets/executions', RunSnippetController::class)
    ->middleware(EnsureKnownProjectMiddleware::class);

Route::get('api/projects', ListProjectsController::class);

Route::prefix('api/projects/{project}/snippets')->middleware(EnsureKnownProjectMiddleware::class)->group(function (): void {
    Route::get('/', ListSnippetsController::class);
    Route::post('/', CreateSnippetController::class)->middleware(HandlePrecognitiveRequests::class);
    Route::put('{snippet}', UpdateSnippetContentController::class);
    Route::patch('{snippet}', UpdateSnippetNameController::class)->middleware(HandlePrecognitiveRequests::class);
    Route::delete('{snippet}', DeleteSnippetController::class);
});

// Not behind EnsureKnownProjectMiddleware: a bare or single-segment URL is ambiguous (a
// pre-existing bookmark like /scratch used to mean "open this snippet"), so the controller
// itself decides whether an unrecognized segment is an unknown project or a snippet name.
// Wayfinder also can't generate a plain callable for a controller split across two routes.
Route::get('{project?}/{snippet?}', OpenSnippetController::class);
