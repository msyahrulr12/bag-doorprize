<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Event extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    protected $fillable = [
        'event_code',
        'event_name',
        'event_image',
        'status',
        'event_started_at',
        'event_ended_at',
        'description',
    ];

    public const EVENT_STATUS = [
        'DRAFT',
        'ACTIVE',
        'COMPLETED'
    ];
}
