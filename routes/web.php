<?php

declare(strict_types=1);

use App\Http\Controllers\CreateSnippetController;
use App\Http\Controllers\DeleteSnippetController;
use App\Http\Controllers\ListProjectsController;
use App\Http\Controllers\ListSnippetsController;
use App\Http\Controllers\OpenSnippetController;
use App\Http\Controllers\RunSnippetController;
use App\Http\Controllers\StartLanguageServerController;
use App\Http\Controllers\UpdateSnippetContentController;
use App\Http\Controllers\UpdateSnippetNameController;
use App\Http\Middleware\EnsureKnownProjectMiddleware;
use App\Http\Middleware\RefreshHerdCacheOnFullPageLoadMiddleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;

Route::pattern('project', '[A-Za-z0-9_-]+');
Route::pattern('snippet', '[A-Za-z0-9_-]+');

Route::post('projects/{project}/snippets/executions', RunSnippetController::class)
    ->middleware(EnsureKnownProjectMiddleware::class);

Route::get('api/projects', ListProjectsController::class);

Route::post('api/projects/{project}/language-server', StartLanguageServerController::class)
    ->middleware(EnsureKnownProjectMiddleware::class);

Route::prefix('api/projects/{project}/snippets')->middleware(EnsureKnownProjectMiddleware::class)->group(function (): void {
    Route::get('/', ListSnippetsController::class);
    Route::post('/', CreateSnippetController::class)->middleware(HandlePrecognitiveRequests::class);
    Route::put('{snippet}', UpdateSnippetContentController::class);
    Route::patch('{snippet}', UpdateSnippetNameController::class)->middleware(HandlePrecognitiveRequests::class);
    Route::delete('{snippet}', DeleteSnippetController::class);
});

// This route also handles the bare URL "/", which opens the developer's current project without
// naming one. EnsureKnownProjectMiddleware always expects a project name in the URL and 404s
// when it's missing, so it can't guard this route. The project check therefore happens inside
// OpenSnippetController itself.
Route::get('{project?}/{snippet?}', OpenSnippetController::class)
    ->middleware(RefreshHerdCacheOnFullPageLoadMiddleware::class);
