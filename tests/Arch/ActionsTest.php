<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
|
| Actions are suffixed by type like every other business-code class and
| expose exactly one public entrypoint, execute() instead of handle(), so
| every caller sees the same shape.
|
*/

arch('actions are suffixed correctly')
    ->expect('App\Actions')
    ->classes()
    ->toHaveSuffix('Action');

arch('actions only expose execute')
    ->expect('App\Actions')
    ->classes()
    ->not->toHavePublicMethodsBesides(['__construct', 'execute']);
