<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Widget extends Model
{
    protected $connection = 'fixture';

    protected $guarded = [];

    public $timestamps = false;

    public function parts(): HasMany
    {
        return $this->hasMany(WidgetPart::class);
    }
}
