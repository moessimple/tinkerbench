<?php

declare(strict_types=1);

use Application\Snippets\Controllers\RunSnippetController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::post('/snippets/executions', RunSnippetController::class)->name('snippets.executions.store');
