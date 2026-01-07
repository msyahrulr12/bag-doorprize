<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'customer_id',
        'account_number',
        'account_type',
        'current_balance',
        'cached_points',
        'description',
    ];
}
