<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Str;

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
        'max_points_required',
        'split_draw',
        'draw_session_id',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function prize()
    {
        return $this->belongsTo(Prize::class);
    }

    public function drawSession()
    {
        return $this->belongsTo(DrawSession::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
            if (is_null($model->remaining_quantity) || $model->remaining_quantity === 0) {
                $model->remaining_quantity = $model->total_quantity;
            }
            if (is_null($model->split_draw) || $model->split_draw === 0) {
                $model->split_draw = $model->total_quantity;
            }
        });
    }
}
