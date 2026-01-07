<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'account_number',
        'account_type',
        'current_balance',
        'cached_points',
        'description',
    ];
}
