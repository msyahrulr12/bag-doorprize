<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Prize extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

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
