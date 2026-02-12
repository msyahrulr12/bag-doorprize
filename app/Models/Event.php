<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Event extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    protected $fillable = [
        'event_code',
        'event_name',
        'event_image',
        'status',
        'event_started_at',
        'event_ended_at',
        'description',
        'last_ticket_number'
    ];

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_COMPLETED = 'COMPLETED';


    public const EVENT_STATUS = [
        self::STATUS_DRAFT => 'DRAFT',
        self::STATUS_ACTIVE => 'ACTIVE',
        self::STATUS_COMPLETED => 'COMPLETED'
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($event) {
            if ($event->status === self::STATUS_ACTIVE) {
                $activeEvent = self::where('status', self::STATUS_ACTIVE)
                    ->where('id', '!=', $event->id)
                    ->exists();

                if ($activeEvent) {
                    throw new \Exception('There is already an active event. Please complete the current active event before activating a new one.');
                }
            }
        });
    }

    public function eventPrizes()
    {
        return $this->hasMany(EventPrize::class);
    }

    public function lotteryTickets()
    {
        return $this->belongsToMany(LotteryTicket::class, 'event_lottery_ticket');
    }

    public function participants()
    {
        return $this->belongsToMany(Participant::class, 'event_participant');
    }

    public function prizes()
    {
        return $this->belongsToMany(Prize::class);
    }
}
