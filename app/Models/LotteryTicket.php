<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LotteryTicket extends Model
{
    protected $fillable = [
        'event_id',
        'participant_id',
        'total_points',
        'range_start',
        'range_end',
    ];
}
