<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class DrawSession extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    protected $fillable = [
        'event_id',
        'name',
        'started_at',
        'ended_at',
        'total_lottery_generated',
        'status',
        'description',
    ];

    public const DRAW_SESSION_STATUS = [
        'ACTIVE',
        'NONACTIVE'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
