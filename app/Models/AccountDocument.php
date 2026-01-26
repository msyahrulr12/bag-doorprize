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
    ];

    protected $casts = [
        'period' => 'date',
        'is_merged' => 'boolean',
        'metadata' => 'json',
    ];

    public const TYPE_ESTATEMENT = 'e-statement';
    public const TYPE = [
        self::TYPE_ESTATEMENT,
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
