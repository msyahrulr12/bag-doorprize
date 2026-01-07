<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPrize extends Model
{
    protected $fillable = [
        'event_id',
        'prize_id',
        'total_quantity',
        'remaining_quantity',
        'min_points_required',
    ];
}
