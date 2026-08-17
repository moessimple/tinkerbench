<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| Datetime Casts
|--------------------------------------------------------------------------
|
| Every column ending in `_at` (besides the timestamps Eloquent already
| casts by default) has to declare an explicit datetime cast, otherwise it
| silently comes back as a string and breaks the first thing that calls a
| Carbon method on it.
|
*/

it('casts every _at column to a datetime', function (): void {
    foreach (glob(__DIR__.'/../../app/Models/*.php') ?: [] as $file) {
        /** @var class-string<Model> $model */
        $model = 'App\Models\\'.basename($file, '.php');

        /** @var Model $instance */
        $instance = Factory::factoryForModel($model)->make();

        $dates = collect($instance->getAttributes())
            ->keys()
            ->filter(fn (string $key): bool => str_ends_with($key, '_at'))
            ->reject(fn (string $key): bool => in_array($key, ['created_at', 'updated_at'], true));

        foreach ($dates as $key) {
            expect($instance->getCasts())->toHaveKey($key, 'datetime', "{$model}'s {$key} is not cast to datetime.");
        }
    }
});
