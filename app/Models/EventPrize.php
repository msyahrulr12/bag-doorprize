<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class EventPrize extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    protected $fillable = [
        'event_id',
        'prize_id',
        'uuid',
        'total_quantity',
        'remaining_quantity',
        'min_points_required',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function prize()
    {
        return $this->belongsTo(Prize::class);
    }
}
