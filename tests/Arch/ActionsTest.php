<?php

declare(strict_types=1);

arch('actions are suffixed correctly')
    ->expect('App\Actions')
    ->classes()
    ->toHaveSuffix('Action');

arch('actions only expose execute')
    ->expect('App\Actions')
    ->classes()
    ->not->toHavePublicMethodsBesides(['__construct', 'execute']);
