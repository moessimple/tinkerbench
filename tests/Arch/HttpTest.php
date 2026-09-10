<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Controllers, Requests, and HTTP Context
|--------------------------------------------------------------------------
|
| The laravel preset already covers the generic HTTP shape rules (controller
| suffix, middleware handle(), form requests extend FormRequest and declare
| rules()). What stays here is the intent that is stricter than the preset:
|
| - Controllers are single-action: __invoke() plus an optional __construct(),
|   not the preset's full REST allowance.
| - Nothing outside the routing layer references a controller class directly.
| - Form requests serve controllers only.
| - session()/auth()/request()/cookie() implicitly read the current HTTP
|   request, so they stay in App\Http and Actions/Support remain callable from
|   any context (a job, a command, a test).
|
*/

arch('controllers stay single-action')
    ->expect('App\Http\Controllers')
    ->classes()
    ->not->toHavePublicMethodsBesides(['__construct', '__invoke']);

arch('controllers are only route targets, never referenced from other code')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

arch('form requests only serve controllers')
    ->expect('App\Http\Requests')
    ->toOnlyBeUsedIn('App\Http\Controllers');

arch('http context helpers stay in the http layer')
    ->expect(['session', 'auth', 'request', 'cookie'])
    ->toOnlyBeUsedIn('App\Http');
