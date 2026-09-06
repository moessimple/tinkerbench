<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WidgetPart extends Model
{
    protected $connection = 'fixture';

    protected $guarded = [];

    public $timestamps = false;
}
