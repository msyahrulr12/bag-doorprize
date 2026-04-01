<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Account extends Model implements Auditable
{
    use SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'product_id',
        'account_number',
        'account_type',
        'current_balance',
        'cached_points',
        'description',
        'status',
        'account_opening_date',
        'account_opening_balance',
    ];

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_EXCLUDE = 'EXCLUDE';
    public const STATUS_CONFI = 'CONFI';

    public const STATUS = [
        self::STATUS_ACTIVE => 'ACTIVE',
        self::STATUS_INACTIVE => 'INACTIVE',
        self::STATUS_EXCLUDE => 'EXCLUDE',
        self::STATUS_CONFI => 'CONFI',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function pointHistories()
    {
        return $this->hasMany(PointHistory::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function documents()
    {
        return $this->hasMany(AccountDocument::class);
    }

    public function participants()
    {
        return $this->hasMany(Participant::class);
    }

    public function lotteryTickets()
    {
        return $this->hasManyThrough(LotteryTicket::class, Participant::class);
    }
}
