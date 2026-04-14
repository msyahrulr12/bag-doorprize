<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Participant extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';

    public const STATUS = [
        self::STATUS_ACTIVE => 'ACTIVE',
        self::STATUS_INACTIVE => 'INACTIVE',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($participant) {
            if ($participant->event_id) {
                $participant->events()->syncWithoutDetaching([$participant->event_id]);
            }
        });

        static::updated(function ($participant) {
            if ($participant->isDirty('event_id') && $participant->event_id) {
                $participant->events()->syncWithoutDetaching([$participant->event_id]);
            }
        });
    }

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

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_participant');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function lotteryTickets()
    {
        return $this->hasMany(LotteryTicket::class)->orderBy('month', 'asc')->orderBy('year', 'asc');
    }

    public function winners()
    {
        return $this->hasMany(Winner::class);
    }

    public function temporaryWinners()
    {
        return $this->hasMany(TemporaryWinner::class);
    }
}
