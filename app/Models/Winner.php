<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Winner extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    protected $fillable = [
        // Participant Details
        'participant_id',
        'participant_cif',
        'participant_account_number',
        'participant_name',
        'participant_email',
        'participant_phone_number',

        // Event Prize
        'event_prize_id',

        // Prize Detail
        'prize_name',
        'prize_tier',
        'prize_total_quantity',
        'prize_value',
        'prize_description',

        // Event Details
        'event_code',
        'event_name',

        // Drawn Details
        'draw_session_id',
        'winning_number',
        'drawn_at',
        'drawn_by',

        // Lottery Ticket
        'lottery_ticket_id',
        'total_points',
        'range_start',
        'range_end',

        // Claim Details
        'status',
        'claimed_by',
        'claimed_at',
    ];

    public const WINNER_STATUS = [
        'PENDING',
        'CLAIMED',
        'EXPIRED',
        'CANCELLED'
    ];
}
