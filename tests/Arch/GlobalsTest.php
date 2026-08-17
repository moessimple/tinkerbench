<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| HTTP Context Helpers
|--------------------------------------------------------------------------
|
| session()/auth()/request() implicitly pull from the current HTTP request.
| Restricting them to App\Http keeps Actions/Support callable from any
| context (a job, a command, a test), not only from behind a web request.
|
*/

arch('http context helpers stay in the http layer')
    ->expect(['session', 'auth', 'request'])
    ->toOnlyBeUsedIn('App\Http');
