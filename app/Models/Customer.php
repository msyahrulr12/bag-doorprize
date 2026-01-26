<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Customer extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

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
        'status',
    ];

    public const STATUS = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function documents()
    {
        return $this->hasMany(AccountDocument::class);
    }
}
