<?php

declare(strict_types=1);

use Application\Snippets\Controllers\OpenSnippetController;
use Application\Snippets\Controllers\RunSnippetController;
use Illuminate\Support\Facades\Route;

Route::pattern('snippet', '[A-Za-z0-9_-]+');

Route::post('snippets/executions', RunSnippetController::class);

Route::get('{snippet?}', OpenSnippetController::class);
