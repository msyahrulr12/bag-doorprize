<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class BulkDrawBatch extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'event_prize_id',
        'draw_session_id',
        'status',
        'total_winners',
        'processed_winners',
        'results',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'results' => 'array',
    ];

    public function eventPrize()
    {
        return $this->belongsTo(EventPrize::class);
    }

    public function drawSession()
    {
        return $this->belongsTo(DrawSession::class);
    }
}
