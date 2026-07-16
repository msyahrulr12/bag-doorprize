<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class PointHistory extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    public static function boot()
    {
        parent::boot();
    }

    protected $fillable = [
        'account_id',
        'amount',
        'month',
        'year',
        'points',
        'type',
        'description',
        'source',
        'unique_key',
        'status',
    ];

    public const POINT_TYPE_EARN = 'EARN';
    public const POINT_TYPE_REDEEM = 'REDEEM';
    public const POINT_TYPE_EXPIRED = 'EXPIRED';
    public const POINT_TYPE_ADJUSTMENT = 'ADJUSTMENT';
    public const POINT_TYPE_RESET = 'RESET';

    public const POINT_HISTORY_TYPE = [
        self::POINT_TYPE_EARN,
        self::POINT_TYPE_REDEEM,
        self::POINT_TYPE_EXPIRED,
        self::POINT_TYPE_ADJUSTMENT,
        self::POINT_TYPE_RESET
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
