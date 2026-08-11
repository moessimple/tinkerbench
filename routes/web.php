<?php

declare(strict_types=1);

use Application\Snippets\Controllers\RunSnippetController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Snippets/Run'));

Route::post('/snippets/executions', RunSnippetController::class);
