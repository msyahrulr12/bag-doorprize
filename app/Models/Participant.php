<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Participant extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    protected $fillable = [
        'event_id',
        'account_id',
        'participant_name',
        'participant_cif',
        'participant_account_number',
        'participant_email',
        'participant_phone_number',
        'total_points_snapshot',
        'range_start',
        'range_end',
        'status',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function lotteryTickets()
    {
        return $this->hasMany(LotteryTicket::class);
    }

    public function winners()
    {
        return $this->hasMany(Winner::class);
    }
}
