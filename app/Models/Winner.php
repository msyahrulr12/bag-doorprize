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

        // Branches
        'branch_id',
        'branch_code',
        'branch_name',
        'branch_company_book',
        'branch_region',

        'account_status'
    ];

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_CLAIMED = 'CLAIMED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_CANCELED = 'CANCELED';


    public const WINNER_STATUS = [
        self::STATUS_PENDING => 'PENDING',
        self::STATUS_CLAIMED => 'CLAIMED',
        self::STATUS_EXPIRED => 'EXPIRED',
        self::STATUS_CANCELED => 'CANCELLED'
    ];

    public const ACCOUNT_STATUS_ACTIVE = 'ACTIVE';
    public const ACCOUNT_STATUS_INACTIVE = 'INACTIVE';
    public const ACCOUNT_STATUS_EXCLUDE = 'EXCLUDE';
    public const ACCOUNT_STATUS_CONFI = 'CONFI';

    public const ACCOUNT_STATUS = [
        self::ACCOUNT_STATUS_ACTIVE => 'ACTIVE',
        self::ACCOUNT_STATUS_INACTIVE => 'INACTIVE',
        self::ACCOUNT_STATUS_EXCLUDE => 'EXCLUDE',
        self::ACCOUNT_STATUS_CONFI => 'CONFI',
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
        $this->load(['participant.account.branch']);

        return [
            'id' => $this->id,
            'cif' => $this->participant_cif,
            'name' => $this->participant_name,
            'account' => [
                'account_number' => $this->participant_account_number,
                'branch' => [
                    'region' => $this->participant->account->branch->region ?? 'N/A',
                    'branch_name' => $this->participant->account->branch->branch_name ?? 'N/A'
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
                'participant_cif' => $this->participant_cif,
                'participant_email' => $this->participant_email,
                'participant_phone_number' => $this->participant_phone_number,
            ],
            'customer' => [
                'id' => $this->participant->account->customer_id ?? null,
            ],
            'lucky_number' => $this->winning_number,
            'winning_number' => $this->range_start === $this->range_end
                ? $this->range_start
                : "{$this->range_start} - {$this->range_end}",
            'region' => $this->participant->account->branch->region ?? 'N/A',
            'branch_name' => $this->participant->account->branch->branch_name ?? 'N/A',
            'drawn_at' => $this->drawn_at ? \Carbon\Carbon::parse($this->drawn_at)->format('Y-m-d H:i:s') : null,
            'product_name' => $this->participant->account->product->nama_produk ?? 'N/A',
            'branch_company_book' => $this->participant->account->branch->company_book ?? 'N/A',
            'account_status' => $this->participant->account->status ?? 'N/A',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
