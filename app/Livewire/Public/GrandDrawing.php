<?php

namespace App\Livewire\Public;

use App\Models\DrawSession;
use App\Models\EventPrize;
use App\Models\LotteryTicket;
use App\Models\Setting;
use App\Models\Winner;
use App\Models\Prize;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GrandDrawing extends Component
{
    public $uuid;
    public $eventPrize;
    public $drawSessionId;
    public $winner = null;
    public $candidates = []; // Real tickets for animation
    public $isDrawing = false;

    public function mount($uuid)
    {
        $this->uuid = $uuid;
        $this->eventPrize = EventPrize::with(['event', 'prize'])->where('uuid', $uuid)->firstOrFail();

        // Auto-select active session if exists
        $this->drawSessionId = DrawSession::where('event_id', $this->eventPrize->event_id)
            ->where('status', 'ACTIVE')
            ->first()?->id;
    }

    public function startDrawing()
    {
        if (!$this->drawSessionId) {
            $this->dispatch('error', message: 'No active draw session selected.');
            return;
        }

        $this->isDrawing = true;
        // Animation delay simulation
        $this->dispatch('trigger-animation');
    }

    public function performDraw()
    {
        $eventId = $this->eventPrize->event_id;

        // Fetch weights from Settings
        $weightsSetting = Setting::where('key', 'region_weights')->first();
        $weights = $weightsSetting ? json_decode($weightsSetting->value, true) : [
            'Jawa' => 50,
            'Sumatera' => 20,
            'Sulawesi' => 20,
            'Lainnya' => 10,
        ];

        // Algorithm to pick target region
        $rand = mt_rand(1, 100);
        $targetRegion = 'Lainnya';
        $cumulative = 0;
        foreach ($weights as $region => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                $targetRegion = $region;
                break;
            }
        }

        // 1. Pick the real winner ticket (Weighted by total_points)
        $winnerTicket = $this->findWinnerInRegion($targetRegion, $eventId);

        if (!$winnerTicket) {
            $otherRegions = array_keys(collect($weights)->sortByDesc(fn($w) => $w)->toArray());
            foreach ($otherRegions as $region) {
                if ($region === $targetRegion)
                    continue;
                $winnerTicket = $this->findWinnerInRegion($region, $eventId);
                if ($winnerTicket)
                    break;
            }
        }

        if (!$winnerTicket) {
            $winnerTicket = $this->findWinnerInRegion(null, $eventId);
        }

        if (!$winnerTicket) {
            $this->isDrawing = false;
            $this->dispatch('error', message: 'No eligible winner found.');
            return;
        }

        // 2. Fetch real candidate lucky numbers for the animation
        $candidateTickets = LotteryTicket::query()
            ->where('event_id', $eventId)
            ->where('total_points', '>=', $this->eventPrize->min_points_required)
            ->where('status', LotteryTicket::STATUS_ACTIVE)
            ->inRandomOrder()
            ->limit(20)
            ->get();

        $this->candidates = $candidateTickets->map(fn($t) => $this->generateLuckyNumber($t))->toArray();

        // 3. Set winner and specific lucky number
        $winnerTicket->load(['participant.account.branch', 'participant.account.customer']);
        $luckyNumber = $this->generateLuckyNumber($winnerTicket);

        $this->winner = [
            'ticket' => $winnerTicket->toArray(),
            'participant' => $winnerTicket->participant->toArray(),
            'customer' => $winnerTicket->participant->account->customer->toArray(),
            'lucky_number' => $luckyNumber, // The specific 9-digit winner
            'winning_number' => $winnerTicket->range_start === $winnerTicket->range_end
                ? $winnerTicket->range_start
                : "{$winnerTicket->range_start} - {$winnerTicket->range_end}",
        ];

        $this->isDrawing = false;
    }

    private function generateLuckyNumber($ticket): string
    {
        $start = $ticket->range_start;
        $end = $ticket->range_end;

        if ($start === $end)
            return $start;

        // Assumes format like A12345678 (Prefix + Digits)
        if (
            preg_match('/^([A-Z]*)(\d+)$/', $start, $startMatch) &&
            preg_match('/^([A-Z]*)(\d+)$/', $end, $endMatch)
        ) {

            $prefix = $startMatch[1];
            $startNum = (int) $startMatch[2];
            $endNum = (int) $endMatch[2];

            $randomNum = mt_rand($startNum, $endNum);
            return $prefix . str_pad($randomNum, strlen($startMatch[2]), '0', STR_PAD_LEFT);
        }

        return $start;
    }

    private function findWinnerInRegion(?string $region, int $eventId): ?LotteryTicket
    {
        $query = LotteryTicket::query()
            ->where('event_id', $eventId)
            ->where('total_points', '>=', $this->eventPrize->min_points_required)
            ->where('status', LotteryTicket::STATUS_ACTIVE)
            ->whereHas('participant.account.customer', function ($q) use ($eventId) {
                $q->whereDoesntHave('accounts.participants.winners', function ($wq) use ($eventId) {
                    $wq->whereHas('eventPrize', fn($eq) => $eq->where('event_prizes.event_id', $eventId));
                });
            });

        if ($region) {
            $query->whereHas('participant.account.branch', function ($q) use ($region) {
                $q->where('region', $region);
            });
        }

        // To be fair, select weighted by the size of their range (total_points)
        $tickets = $query->get();
        if ($tickets->isEmpty())
            return null;

        $totalPoints = $tickets->sum('total_points');
        $winningOffset = mt_rand(1, $totalPoints);

        $currentOffset = 0;
        foreach ($tickets as $ticket) {
            $currentOffset += $ticket->total_points;
            if ($winningOffset <= $currentOffset) {
                return $ticket;
            }
        }

        return $tickets->first();
    }

    public function confirmWinner()
    {
        if (!$this->winner)
            return;

        // Re-check
        $this->eventPrize->refresh();
        if ($this->eventPrize->remaining_quantity <= 0) {
            $this->dispatch('error', message: 'Prize exhausted.');
            $this->winner = null;
            return;
        }

        // Handle array or object access
        $winnerData = $this->winner;
        $ticketId = $winnerData['ticket']['id'] ?? null;
        $participantId = $winnerData['participant']['id'] ?? null;

        Winner::create([
            'participant_id' => $participantId,
            'participant_cif' => $winnerData['participant']['participant_cif'],
            'participant_account_number' => $winnerData['participant']['participant_account_number'],
            'participant_name' => $winnerData['participant']['participant_name'],
            'participant_email' => $winnerData['participant']['participant_email'],
            'participant_phone_number' => $winnerData['participant']['participant_phone_number'],
            'event_prize_id' => $this->eventPrize->id,
            'prize_name' => $this->eventPrize->prize->prize_name,
            'prize_tier' => Prize::PRIZE_TIER[$this->eventPrize->prize->tier] ?? 'Common',
            'prize_total_quantity' => $this->eventPrize->total_quantity,
            'prize_value' => $this->eventPrize->prize->value,
            'prize_description' => $this->eventPrize->prize->description,
            'event_code' => $this->eventPrize->event->event_code,
            'event_name' => $this->eventPrize->event->event_name,
            'draw_session_id' => $this->drawSessionId,
            'winning_number' => $this->winner['winning_number'],
            'drawn_at' => now(),
            'drawn_by' => Auth::user()->name ?? 'Guest User',
            'lottery_ticket_id' => $ticketId,
            'total_points' => $winnerData['ticket']['total_points'],
            'range_start' => $winnerData['ticket']['range_start'],
            'range_end' => $winnerData['ticket']['range_end'],
            'status' => 'PENDING',
        ]);

        $this->eventPrize->decrement('remaining_quantity');

        $this->dispatch('winner-confirmed');
        $this->winner = null;
    }

    public function render()
    {
        return view('livewire.public.grand-drawing')
            ->layout('layouts.guest');
    }
}
