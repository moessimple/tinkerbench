<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Plain Value Enums
|--------------------------------------------------------------------------
|
| The laravel preset already asserts everything in App\Enums is a real enum.
| This adds the stricter house rule: no traits, no imported collaborators.
| Enums stay plain domain nouns with no behavior of their own.
|
*/

arch('enums stay plain value types')
    ->expect('App\Enums')
    ->toUseNothing();
