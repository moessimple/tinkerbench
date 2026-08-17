<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Plain Value Enums
|--------------------------------------------------------------------------
|
| Enums stay plain domain nouns, no behavior of their own: no
| extending/implementing anything, no traits.
|
*/

arch('enums stay plain value types')
    ->expect('App\Enums')
    ->toExtendNothing()
    ->toUseNothing();
