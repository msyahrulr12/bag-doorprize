<?php

namespace App\Models;

use Filament\Notifications\Notification;
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
        'last_ticket_number',
        'public_draw_background'
    ];

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_INACTIVE = 'INACTIVE';

    public const EVENT_STATUS = [
        self::STATUS_DRAFT => 'DRAFT',
        self::STATUS_ACTIVE => 'ACTIVE',
        self::STATUS_COMPLETED => 'COMPLETED',
        self::STATUS_INACTIVE => 'INACTIVE'
    ];

    protected static function boot()
    {
        parent::boot();

        // static::saving(function ($event) {
        //     if ($event->status === self::STATUS_ACTIVE) {
        //         $activeEvent = self::where('status', self::STATUS_ACTIVE)
        //             ->where('id', '!=', $event->id)
        //             ->exists();

        //         if ($activeEvent) {
        //             Notification::make()
        //                 ->title('There is already an active event. Please complete the current active event before activating a new one.')
        //                 ->warning()
        //                 ->send();
        //         }
        //     }
        // });
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

    public function drawSessions()
    {
        return $this->hasMany(DrawSession::class);
    }

    public function winners()
    {
        return $this->hasManyThrough(
            Winner::class,
            DrawSession::class,
            'event_id',
            'draw_session_id',
            'id',
            'id'
        );
    }

    public function activeParticipants()
    {
        return $this->hasMany(Participant::class, 'event_id')->where('status', Participant::STATUS_ACTIVE);
    }

    public function randomParticipants()
    {
        return $this->hasMany(Participant::class, 'event_id')->with(['account', 'account.branch', 'lotteryTickets'])->where('status', Participant::STATUS_ACTIVE)->limit(1000);
    }

    public function activeLotteryTickets()
    {
        return $this->hasMany(LotteryTicket::class, 'event_id')->where('status', LotteryTicket::STATUS_ACTIVE);
    }
}
