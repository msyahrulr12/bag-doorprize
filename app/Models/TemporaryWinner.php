<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class TemporaryWinner extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    protected $fillable = [
        'participant_id',
        'participant_cif',
        'participant_account_number',
        'participant_name',
        'participant_email',
        'participant_phone_number',
        'event_prize_id',
        'draw_session_id',
        'winning_number',
        'drawn_at',
        'drawn_by',
        'lottery_ticket_id',
        'total_points',
        'range_start',
        'range_end',
        'status',
        'branch_id',
        'branch_code',
        'branch_name',
        'branch_company_book',
        'branch_region',
        'account_status'
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

    /**
     * Map this temp winner data to a format compatible with front-end layouts
     */
    public function getData()
    {
        return [
            'lucky_number' => $this->winning_number,
            'branch_name' => $this->branch_name,
            'cif' => $this->participant_cif,
            'account' => [
                'account_number' => $this->participant_account_number,
                'branch' => [
                    'branch_name' => $this->branch_name,
                    'region' => $this->branch_region,
                ]
            ],
            'participant' => [
                'participant_name' => $this->participant_name,
                'participant_cif' => $this->participant_cif,
                'account' => [
                    'branch' => [
                        'branch_name' => $this->branch_name,
                    ]
                ]
            ],
            'ticket' => [
                'id' => $this->lottery_ticket_id,
                'total_points' => $this->total_points,
                'range_start' => $this->range_start,
                'range_end' => $this->range_end,
            ]
        ];
    }
}
