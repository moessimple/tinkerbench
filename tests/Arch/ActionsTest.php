<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
|
| Actions are suffixed by type like every other business-code class and
| expose exactly one public entrypoint, handle(), matching the starter kit
| convention, so every caller sees the same shape.
|
*/

arch('actions are suffixed correctly')
    ->expect('App\Actions')
    ->classes()
    ->toHaveSuffix('Action')
    ->toExtendNothing();

arch('actions only expose handle')
    ->expect('App\Actions')
    ->classes()
    ->not->toHavePublicMethodsBesides(['__construct', 'handle']);
