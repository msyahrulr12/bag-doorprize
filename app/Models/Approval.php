<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Approval extends Model implements Auditable
{
    use \Illuminate\Database\Eloquent\SoftDeletes, \OwenIt\Auditing\Auditable;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'model_type',
        'model_id',
        'resource',
        'action',
        'original_data',
        'new_data',
        'user_id',
        'approver_id',
        'status',
        'reason',
        'action_at',
    ];

    protected $casts = [
        'original_data' => 'array',
        'new_data' => 'array',
        'action_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\Modules\UserManagement\Models\User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(\Modules\UserManagement\Models\User::class, 'approver_id');
    }

    public function model()
    {
        return $this->morphTo();
    }
}
