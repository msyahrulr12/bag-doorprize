<?php

namespace App\Listeners;

use App\Events\PointHistoryProcessed;
use App\Models\LotteryTicket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Log;

class CreateLotteryTickets implements ShouldQueue
{
    public $queue = 'tickets';

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PointHistoryProcessed $event): void
    {
        Log::info('Processing Create Lottery Tickets');
        $pointHistoriesRaw = $event->pointHistories;
        $participantMap = $event->participantMap;
        $eventId = $event->eventId;

        Log::info('point history: ' . json_encode($pointHistoriesRaw));
        Log::info('participant: ' . json_encode($participantMap));
        Log::info('event ID: ' . $eventId);

        // 1. Prepare data and calculate total points for this chunk
        $totalPointsInChunk = 0;
        $pointsMap = [];
        foreach ($pointHistoriesRaw as $ph) {
            if (isset($ph['points']) && $ph['points'] > 0) {
                $totalPointsInChunk += $ph['points'];
                $pointsMap[$ph['account_id']] = $ph['points'];
            }
        }

        if ($totalPointsInChunk <= 0) {
            return;
        }

        // 2. Atomic reservation of the ticket number range
        // Using lockForUpdate to prevent race conditions during the counter increment
        $startTicketNumber = \DB::transaction(function () use ($eventId, $totalPointsInChunk) {
            $eventRecord = \App\Models\Event::where('id', $eventId)
                ->lockForUpdate()
                ->first();

            $currentLast = (int) ($eventRecord->last_ticket_number ?? 0);
            $newLast = $currentLast + $totalPointsInChunk;

            $eventRecord->update(['last_ticket_number' => $newLast]);

            return $currentLast;
        });

        // 3. Assign tickets within the reserved range
        $currentNumber = $startTicketNumber;
        foreach ($participantMap as $accountId => $participantId) {
            Log::info('Assigning ticket with the reserved range: ' . $participantId);
            $points = $pointsMap[$accountId] ?? 0;
            if ($points <= 0) {
                continue;
            }

            // Calculate formatted range (Step Id: 399 logic)
            $range_start = chr(65 + intdiv($currentNumber, 99999999)) .
                str_pad($currentNumber % 99999999 + 1, 8, '0', STR_PAD_LEFT);

            $currentNumber += $points;

            $range_end = chr(65 + intdiv($currentNumber - 1, 99999999)) .
                str_pad(($currentNumber - 1) % 99999999 + 1, 8, '0', STR_PAD_LEFT);

            LotteryTicket::updateOrCreate([
                'event_id' => $eventId,
                'participant_id' => $participantId,
                'month' => $event->month,
                'year' => $event->year,
            ], [
                'total_points' => $points,
                'range_start' => $range_start,
                'range_end' => $range_end,
                'status' => LotteryTicket::STATUS_ACTIVE,
                'description' => "Monthly lottery tickets for {$event->month}/{$event->year}",
                'updated_at' => now(),
            ]);
        }
    }
}
