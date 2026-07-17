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
        'nik',
        'npwp',
        'email',
        'phone_number',
        'address',
        'description',
        'total_point_sum',
        'redeemed_points',
        'status',
        'date_of_birth',
    ];

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS = [
        self::STATUS_ACTIVE => 'ACTIVE',
        self::STATUS_INACTIVE => 'INACTIVE',
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
