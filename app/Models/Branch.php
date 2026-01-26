<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Modules\UserManagement\Models\User;

class Branch extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    protected $fillable = [
        'branch_code',
        'branch_name',
        'address',
        'description',
        'sk_branch',
        'sandi_pelapor_kantor_lbu',
        'nama_sandi_pelapor',
        'company_book',
        'company_name',
        'name_address',
        'date_from',
        'date_to',
        'version',
        'wib',
        'update_date',
        'update_regional1',
        'update_date1',
        'new_regional_head',
        'status',
        'region',
    ];

    public const REGIONS = [
        'Jawa' => 'Jawa',
        'Sumatera' => 'Sumatera',
        'Sulawesi' => 'Sulawesi',
        'Lainnya' => 'Lainnya',
    ];

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS = [
        self::STATUS_ACTIVE => 'ACTIVE',
        self::STATUS_INACTIVE => 'INACTIVE',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_branches', 'branch_id', 'user_id');
    }
}
