<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class LotteryTicket extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    protected $fillable = [
        'event_id',
        'participant_id',
        'total_points',
        'range_start',
        'range_end',
        'status',
        'month',
        'year',
        'description',
    ];

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_RESET = 'RESET';
    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS = [
        self::STATUS_ACTIVE,
        self::STATUS_RESET,
        self::STATUS_COMPLETED,
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}
