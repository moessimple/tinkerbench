<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Controllers, Requests, Middleware
|--------------------------------------------------------------------------
|
| Controllers here are single-action only (__invoke() plus an optional
| __construct()), stricter than the laravel preset's own allowance (see
| tests/ArchTest.php), which permits the full REST method set too.
|
*/

arch('controllers stay single-action')
    ->expect('App\Http\Controllers')
    ->classes()
    ->not->toHavePublicMethodsBesides(['__construct', '__invoke']);
