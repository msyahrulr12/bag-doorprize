<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'event_code',
        'event_name',
        'event_image',
        'status',
        'event_started_at',
        'event_ended_at',
        'description',
    ];
}
