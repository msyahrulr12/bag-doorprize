<?php

namespace App\Listeners;

use App\Events\PointHistoryProcessed;
use App\Models\Event;
use App\Models\LotteryTicket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateLotteryTickets implements ShouldQueue
{
    public $queue = 'tickets';

    /**
     * Handle the event.
     */
    public function handle(PointHistoryProcessed $event): void
    {
        $pointHistories = $event->pointHistories;
        $participantMap = $event->participantMap;
        $eventId = $event->eventId;
        $month = $event->month;
        $year = $event->year;

        // 1. Calculate total points in this chunk
        $validPoints = [];
        $totalPointsInChunk = 0;
        foreach ($pointHistories as $ph) {
            if (isset($ph['points']) && $ph['points'] > 0) {
                $accId = $ph['account_id'];
                $validPoints[$accId] = [
                    'points' => (int) $ph['points'],
                    'description' => $ph['description'],
                    'source' => $ph['source'] ?? 'SYSTEM'
                ];
                $totalPointsInChunk += (int) $ph['points'];
            }
        }

        if ($totalPointsInChunk <= 0) {
            return;
        }

        // 2. Reserved ticket range (Atomic)
        // Multiple chunks will queue here for the event lock. 
        // We use a small transaction to keep lock time minimal.
        $startTicketNumber = DB::transaction(function () use ($eventId, $totalPointsInChunk) {
            $eventRecord = Event::where('id', $eventId)->lockForUpdate()->first();
            $currentLast = (int) ($eventRecord->last_ticket_number ?? 0);
            $eventRecord->update(['last_ticket_number' => $currentLast + $totalPointsInChunk]);
            return $currentLast;
        });

        // 3. Prepare Batch Upsert
        $currentNumber = $startTicketNumber;
        $upserts = [];
        $now = now();

        foreach ($participantMap as $accountId => $participantId) {
            $data = $validPoints[$accountId] ?? null;
            if (!$data)
                continue;

            $points = $data['points'];

            // Format start/end
            $range_start = \App\Utils\TicketHelper::format($currentNumber);
            $currentNumber += $points;
            $range_end = \App\Utils\TicketHelper::format($currentNumber - 1);

            $uniqueKey = "lt_sys_{$eventId}_{$participantId}_{$month}_{$year}";
            $upserts[] = [
                'event_id' => $eventId,
                'participant_id' => $participantId,
                'month' => $month,
                'year' => $year,
                'total_points' => $points,
                'range_start' => $range_start,
                'range_end' => $range_end,
                'status' => LotteryTicket::STATUS_ACTIVE,
                'description' => $data['description'],
                'source' => $data['source'],
                'unique_key' => $uniqueKey,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($upserts)) {
            // High speed upsert using the unique_key column
            LotteryTicket::upsert($upserts, ['unique_key'], ['total_points', 'range_start', 'range_end', 'status', 'description', 'updated_at']);
        }

        Log::info(sprintf('✓ Assigned %d points to %d participants (Reserved Range: %d to %d)', $totalPointsInChunk, count($upserts), $startTicketNumber, $currentNumber - 1));
    }
}
