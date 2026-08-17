<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;

/*
|--------------------------------------------------------------------------
| Controllers, Requests, Middleware
|--------------------------------------------------------------------------
|
| Controllers here are single-action only (__invoke() plus an optional
| __construct()), stricter than the laravel preset's own allowance (see
| tests/ArchTest.php), which permits the full REST method set too. Middleware
| here is suffixed Middleware; the preset has no suffix rule for
| App\Http\Middleware.
|
*/

arch('controllers stay single-action')
    ->expect('App\Http\Controllers')
    ->classes()
    ->not->toHavePublicMethodsBesides(['__construct', '__invoke']);

arch('middleware are suffixed correctly')
    ->expect('App\Http\Middleware')
    ->classes()
    ->toHaveSuffix('Middleware')
    ->ignoring(HandleInertiaRequests::class);
