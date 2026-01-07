<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class PointHistory extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    protected $fillable = [
        'account_id',
        'amount',
        'month',
        'year',
        'points',
        'type',
        'description',
    ];

    public const POINT_HISTORY_TYPE = [
        'EARN',
        'REDEEM',
        'EXPIRED',
        'ADJUSTMENT'
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
