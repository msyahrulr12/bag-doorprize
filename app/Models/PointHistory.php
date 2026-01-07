<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointHistory extends Model
{
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
