<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Prize extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    protected $fillable = [
        'prize_code',
        'prize_name',
        'prize_image',
        'tier',
        'value',
        'description',
        'status',
    ];

    public const TIER_GRAND_PRIZE = 'GRAND PRIZE';
    public const TIER_1 = 'TIER 1';
    public const TIER_2 = 'TIER 2';
    public const TIER_3 = 'TIER 3';
    public const TIER_COMMON = 'COMMON';

    public const PRIZE_TIER = [
        self::TIER_GRAND_PRIZE => self::TIER_GRAND_PRIZE,
        self::TIER_1 => self::TIER_1,
        self::TIER_2 => self::TIER_2,
        self::TIER_3 => self::TIER_3,
        self::TIER_COMMON => self::TIER_COMMON,
    ];

    public function events()
    {
        return $this->belongsToMany(Event::class);
    }
}
