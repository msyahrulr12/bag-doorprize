<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ApprovalConfig extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'resource',
        'action',
        'is_enabled',
        'approver_role',
    ];

    public static function isRequired(string $resource, string $action): bool
    {
        $config = self::where('resource', $resource)
            ->where(function ($query) use ($action) {
                $query->where('action', $action)
                    ->orWhere('action', 'all');
            })
            ->where('is_enabled', true)
            ->first();

        return $config !== null;
    }

    public static function getApproverRole(string $resource, string $action): string
    {
        $config = self::where('resource', $resource)
            ->where(function ($query) use ($action) {
                $query->where('action', $action)
                    ->orWhere('action', 'all');
            })
            ->where('is_enabled', true)
            ->first();

        return $config->approver_role ?? 'super_admin';
    }
}
