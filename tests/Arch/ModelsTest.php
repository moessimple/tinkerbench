<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| Models
|--------------------------------------------------------------------------
|
| Every model extends the base Eloquent model.
|
*/

arch('models extend the base Eloquent model')
    ->expect('App\Models')
    ->toExtend(Model::class);
