<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Factories\Factory;

arch('factories extend the base factory, define state, and are used only by models')
    ->expect('Database\Factories')
    ->toExtend(Factory::class)
    ->toHaveMethod('definition')
    ->toOnlyBeUsedIn('App\Models');
