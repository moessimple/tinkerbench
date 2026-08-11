<?php

declare(strict_types=1);

arch('domain does not depend on application')
    ->expect('Domain')
    ->not->toUse('Application');

arch('domain does not depend on the http/routing framework layer')
    ->expect('Domain')
    ->not->toUse(['Illuminate\Http', 'Illuminate\Routing']);
