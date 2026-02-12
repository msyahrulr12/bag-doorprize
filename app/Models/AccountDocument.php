<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountDocument extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'customer_id',
        'account_id',
        'type',
        'filename',
        'path',
        'period',
        'is_merged',
        'status',
        'metadata',
        'document_type',
        'file_description'
    ];

    protected $casts = [
        'period' => 'date',
        'is_merged' => 'boolean',
        'metadata' => 'json',
    ];

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS = [
        self::STATUS_PENDING => 'PENDING',
        self::STATUS_ACTIVE => 'ACTIVE',
        self::STATUS_INACTIVE => 'INACTIVE',
    ];

    public const TYPE_ESTATEMENT = 'E-STATEMENT';
    public const TYPE_FULL_ESTATEMENT = 'FULL E-STATEMENT';
    public const TYPE = [
        self::TYPE_ESTATEMENT => 'E-STATEMENT',
        self::TYPE_FULL_ESTATEMENT => 'FULL E-STATEMENT',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
