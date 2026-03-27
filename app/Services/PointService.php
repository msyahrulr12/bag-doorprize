<?php

namespace App\Services;

use App\Models\Account;
use App\Models\PointHistory;
use App\Models\Participant;
use App\Models\LotteryTicket;
use App\Models\Event;
use App\Models\Winner;
use App\Utils\TicketHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PointService
{
    /**
     * Execute point correction logic.
     */
    public function executeCorrection(array $data)
    {
        $accountId = $data['account_id'];
        $type = $data['type'];
        $points = (int) $data['points'];
        $description = $data['description'];
        $month = $data['month'] ?? now()->month;
        $year = $data['year'] ?? now()->year;

        $account = Account::findOrFail($accountId);
        $activeEvent = Event::where('status', Event::STATUS_ACTIVE)->first();

        if (!$activeEvent) {
            throw new \Exception('No active event found');
        }

        DB::beginTransaction();
        try {
            // 1. Log the Point History
            PointHistory::create([
                'account_id' => $accountId,
                'amount' => $account->current_balance,
                'month' => $month,
                'year' => $year,
                'points' => $type === PointHistory::POINT_TYPE_EARN ? $points : -$points,
                'type' => $type,
                'description' => "[Correction REK ({$accountId})] " . $description,
                'source' => 'MANUAL',
            ]);

            // 2. Find Participant
            $participant = Participant::where('event_id', $activeEvent->id)
                ->where('account_id', $accountId)
                ->first();

            if (!$participant) {
                $participant = Participant::create([
                    'event_id' => $activeEvent->id,
                    'account_id' => $accountId,
                    'participant_name' => $account->customer->name ?? 'Unknown',
                    'participant_cif' => $account->customer->cif ?? '000000',
                    'participant_account_number' => $account->account_number,
                    'participant_email' => $account->customer->email ?? '',
                    'status' => Participant::STATUS_ACTIVE,
                ]);
            }

            if ($type === PointHistory::POINT_TYPE_EARN) {
                if ($points > 0) {
                    $this->processEarn($participant, $activeEvent, $points, $description, $month, $year);
                }
            } else {
                $this->processExpired($participant, $activeEvent, $points, $description, $month, $year);
            }

            DB::commit();

            // 3. Regenerate Bank Statement after point correction
            try {
                $bankStatementService = app(\App\Services\BankStatementService::class);
                $bankStatementService->generateForAccount($accountId, $month, $year);
            } catch (\Exception $e) {
                Log::error("Bank Statement Generation Error after Point Correction: " . $e->getMessage());
            }

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Point Service Error: " . $e->getMessage());
            throw $e;
        }
    }

    private function processEarn($participant, $event, $points, $description, $month, $year)
    {
        $startTicketNumber = DB::transaction(function () use ($event, $points) {
            $eventRecord = Event::where('id', $event->id)->lockForUpdate()->first();
            $currentLast = (int) ($eventRecord->last_ticket_number ?? 0);
            $newLast = $currentLast + $points;
            $eventRecord->update(['last_ticket_number' => $newLast]);
            return $currentLast;
        });

        $rangeStart = TicketHelper::format($startTicketNumber);
        $rangeEnd = TicketHelper::format($startTicketNumber + $points - 1);

        $accountNumber = $participant->participant_account_number;

        LotteryTicket::create([
            'event_id' => $event->id,
            'participant_id' => $participant->id,
            'month' => $month,
            'year' => $year,
            'total_points' => $points,
            'range_start' => $rangeStart,
            'range_end' => $rangeEnd,
            'status' => LotteryTicket::STATUS_ACTIVE,
            'description' => "[Correction EARN REK ({{$accountNumber}})] " . $description,
            'source' => 'MANUAL',
        ]);
    }

    private function processExpired($participant, $event, $pointsToSubtract, $description, $month = null, $year = null)
    {
        // If pointsToSubtract is 0, we can Interpretation as "Remove ALL points/tickets" 
        // But for "many times correction", we probably want to support fractional subtractions.
        $removeAll = ($pointsToSubtract === 0);
        $remaining = $removeAll ? PHP_INT_MAX : $pointsToSubtract;

        if (!$removeAll && $remaining <= 0) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\LotteryTicket> $tickets */
        $tickets = LotteryTicket::where('participant_id', $participant->id)
            ->where('event_id', $event->id)
            // ->when($month && $year, fn($q) => $q->where('month', $month)->where('year', $year))
            ->where('status', LotteryTicket::STATUS_ACTIVE)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($tickets as $ticket) {
            if (!$removeAll && $remaining <= 0) {
                break;
            }

            $currentPoints = $ticket->total_points;
            $winner = Winner::where('lottery_ticket_id', $ticket->id)->first();

            if ($removeAll || $currentPoints <= $remaining) {
                // This ticket can be fully removed or its points are less than or equal to remaining
                if ($winner) {
                    if ($removeAll) {
                        // If removing all, cancel winner and delete ticket
                        $winner->update(['status' => Winner::STATUS_CANCELED]);
                        $ticket->delete();
                        $remaining -= $currentPoints; // Account for points removed
                    } else {
                        // If not removing all, and it's a winner, keep at least 1 point
                        $possibleToTake = $currentPoints - 1;
                        if ($possibleToTake > 0) {
                            $this->reduceTicketRange($ticket, $possibleToTake, $winner);
                            $remaining -= $possibleToTake;
                        }
                    }
                } else {
                    // No winner, just delete the ticket
                    $remaining -= $currentPoints;
                    $ticket->delete();
                }
            } else {
                // This ticket has more points than we need to subtract, so partially reduce it
                $this->reduceTicketRange($ticket, $remaining, $winner);
                $remaining = 0;
            }
        }
    }

    private function reduceTicketRange($ticket, $pointsToRemove, $winner = null)
    {
        if ($pointsToRemove <= 0)
            return;

        $startInt = TicketHelper::parse($ticket->range_start);
        $endInt = TicketHelper::parse($ticket->range_end);

        if ($winner) {
            $winningNumInt = TicketHelper::parse($winner->winning_number);

            // Check if removing from the end hits the winner
            if ($winningNumInt > ($endInt - $pointsToRemove)) {
                // Hits winner! Remove from the start (first range) instead.
                $newStartInt = $startInt + $pointsToRemove;
                $ticket->update([
                    'total_points' => $ticket->total_points - $pointsToRemove,
                    'range_start' => TicketHelper::format($newStartInt),
                    'description' => $ticket->description . " (Corrected START: -$pointsToRemove)"
                ]);
                return;
            }
        }

        // Default: remove from end
        $newEndInt = $endInt - $pointsToRemove;
        $ticket->update([
            'total_points' => $ticket->total_points - $pointsToRemove,
            'range_end' => TicketHelper::format($newEndInt),
            'description' => $ticket->description . " (Corrected END: -$pointsToRemove)"
        ]);
    }
}
