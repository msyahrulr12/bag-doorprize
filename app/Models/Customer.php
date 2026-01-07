<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'cif',
        'email',
        'phone_number',
        'address',
        'description',
        'total_point_sum',
        'redeemed_points',
    ];
}
