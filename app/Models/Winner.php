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

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }

    public function eventPrize()
    {
        return $this->belongsTo(EventPrize::class);
    }

    public function drawSession()
    {
        return $this->belongsTo(DrawSession::class);
    }

    public function lotteryTicket()
    {
        return $this->belongsTo(LotteryTicket::class);
    }

    public function getDataBulk()
    {
        return [
            'cif' => $this->participant_cif,
            'name' => $this->participant_name,
            'account' => [
                'account_number' => $this->participant_account_number,
                'branch' => [
                    'region' => $this->participant_region
                ],
            ],
            'ticket' => [
                'id' => $this->lottery_ticket_id,
                'total_points' => $this->total_points,
                'range_start' => $this->range_start,
                'range_end' => $this->range_end,
            ],
            'participant' => [
                'id' => $this->participant_id,
                'participant_name' => $this->participant_name,
                'participant_cif' => $this->participant_cif, // Actually customer cif
                'participant_email' => $this->participant_email,
            ],
            'customer' => [
                'id' => $this->customer_id,
            ],
            'lucky_number' => $this->winning_number,
            'winning_number' => $this->range_start === $this->range_end
                ? $this->range_start
                : "{$this->range_start} - {$this->range_end}",
            'region' => $this->region
        ];
    }
}
