<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawSession extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'started_at',
        'ended_at',
        'total_lottery_generated',
        'status',
        'description',
    ];
}
