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
                'month' => now()->month,
                'year' => now()->year,
                'points' => $type === PointHistory::POINT_TYPE_EARN ? $points : -$points,
                'type' => $type,
                'description' => "[Correction] " . $description,
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
                $this->processEarn($participant, $activeEvent, $points, $description);
            } else {
                $this->processExpired($participant, $activeEvent, $points, $description);
            }

            DB::commit();

            // 3. Regenerate Bank Statement after point correction
            try {
                $bankStatementService = app(\App\Services\BankStatementService::class);
                $bankStatementService->generateForAccount($accountId, now()->month, now()->year);
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

    private function processEarn($participant, $event, $points, $description)
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

        LotteryTicket::create([
            'event_id' => $event->id,
            'participant_id' => $participant->id,
            'month' => now()->month,
            'year' => now()->year,
            'total_points' => $points,
            'range_start' => $rangeStart,
            'range_end' => $rangeEnd,
            'status' => LotteryTicket::STATUS_ACTIVE,
            'description' => "[Correction EARN] " . $description,
        ]);
    }

    private function processExpired($participant, $event, $pointsToSubtract, $description)
    {
        $tickets = LotteryTicket::where('participant_id', $participant->id)
            ->where('event_id', $event->id)
            ->where('status', LotteryTicket::STATUS_ACTIVE)
            ->orderBy('id', 'desc')
            ->get();

        $remaining = $pointsToSubtract;

        foreach ($tickets as $ticket) {
            if ($remaining <= 0)
                break;

            $isWinner = Winner::where('lottery_ticket_id', $ticket->id)->exists();
            if ($isWinner)
                continue;

            $currentPoints = $ticket->total_points;

            if ($currentPoints <= $remaining) {
                $remaining -= $currentPoints;
                $ticket->delete();
            } else {
                $newPoints = $currentPoints - $remaining;
                $startInt = TicketHelper::parse($ticket->range_start);
                $newEndInt = $startInt + $newPoints - 1;

                $ticket->update([
                    'total_points' => $newPoints,
                    'range_end' => TicketHelper::format($newEndInt),
                    'description' => $ticket->description . " (Corrected: -$remaining points)"
                ]);

                $remaining = 0;
            }
        }

        if ($remaining > 0) {
            throw new \Exception("Could not subtract all points. Remaining: $remaining points (Winner tickets were skipped).");
        }
    }
}
