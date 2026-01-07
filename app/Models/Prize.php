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
    ];

    public const PRIZE_TIER = [
        'Grand Prize',
        'Tier 1',
        'Tier 2',
        'Tier 3',
        'Common',
    ];
}
