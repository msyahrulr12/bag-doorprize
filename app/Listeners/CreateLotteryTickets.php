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

        // 1. Build valid points map (only positive points qualify for tickets)
        $validPoints = [];
        foreach ($pointHistories as $ph) {
            if (isset($ph['points']) && $ph['points'] > 0) {
                $accId = $ph['account_id'];
                $validPoints[$accId] = [
                    'points'      => (int) $ph['points'],
                    'description' => $ph['description'],
                    'source'      => $ph['source'] ?? 'SYSTEM',
                ];
            }
        }

        if (empty($validPoints)) {
            return;
        }

        // 2. Separate participants into NEW vs EXISTING (already have a ticket for this month/year)
        //    Existing entries must NOT consume a new number range — we just update their metadata.
        $newParticipants      = [];  // need a fresh ticket range
        $existingParticipants = [];  // already have range_start; update in-place

        foreach ($participantMap as $accountId => $participantId) {
            $data = $validPoints[$accountId] ?? null;
            if (!$data) {
                continue;
            }

            $uniqueKey = "lt_sys_{$eventId}_{$participantId}_{$month}_{$year}";

            $existing = LotteryTicket::where('unique_key', $uniqueKey)->first();
            if ($existing) {
                $existingParticipants[] = [
                    'participant_id' => $participantId,
                    'unique_key'     => $uniqueKey,
                    'existing'       => $existing,
                    'data'           => $data,
                ];
            } else {
                $newParticipants[] = [
                    'participant_id' => $participantId,
                    'unique_key'     => $uniqueKey,
                    'data'           => $data,
                ];
            }
        }

        // 3. Reserve ticket numbers ONLY for truly new participants (Atomic)
        $totalNewPoints = array_sum(array_map(fn($p) => $p['data']['points'], $newParticipants));

        $startTicketNumber = 0;
        if ($totalNewPoints > 0) {
            $startTicketNumber = DB::transaction(function () use ($eventId, $totalNewPoints) {
                $eventRecord = Event::where('id', $eventId)->lockForUpdate()->first();
                $currentLast = (int) ($eventRecord->last_ticket_number ?? 0);

                // Add validation based on latest data on lottery_tickets
                $latestTicket = LotteryTicket::where('event_id', $eventId)
                    ->orderByDesc('range_end')
                    ->first();

                if ($latestTicket) {
                    $latestParsed = \App\Utils\TicketHelper::parse($latestTicket->range_end) + 1;
                    if ($latestParsed > $currentLast) {
                        $currentLast = $latestParsed;
                    }
                }

                $eventRecord->update(['last_ticket_number' => $currentLast + $totalNewPoints]);
                return $currentLast;
            });
        }

        $now = now();
        $upserts = [];

        // 4a. Assign fresh ranges to new participants
        $currentNumber = $startTicketNumber;
        foreach ($newParticipants as $entry) {
            $points      = $entry['data']['points'];
            $range_start = \App\Utils\TicketHelper::format($currentNumber);
            $currentNumber += $points;
            $range_end   = \App\Utils\TicketHelper::format($currentNumber - 1);

            $upserts[] = [
                'event_id'       => $eventId,
                'participant_id' => $entry['participant_id'],
                'month'          => $month,
                'year'           => $year,
                'total_points'   => $points,
                'range_start'    => $range_start,
                'range_end'      => $range_end,
                'status'         => LotteryTicket::STATUS_ACTIVE,
                'description'    => $entry['data']['description'],
                'source'         => $entry['data']['source'],
                'unique_key'     => $entry['unique_key'],
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        // 4b. For existing participants, keep their current range_start but update range_end
        //     to reflect the latest point count (points may have changed on re-import).
        foreach ($existingParticipants as $entry) {
            $existing    = $entry['existing'];
            $points      = $entry['data']['points'];
            $oldPoints   = $existing->total_points;

            if ($points > $oldPoints) {
                // If points increased, we cannot simply extend range_end because it will overlap with the next user's ticket.
                // For safety in production, we will NOT extend the range to prevent overlaps, but we log a critical warning.
                Log::warning(sprintf(
                    "Cannot safely increase ticket points for %s from %d to %d without overlapping. Keeping at %d.",
                    $existing->unique_key,
                    $oldPoints,
                    $points,
                    $oldPoints
                ));
                $points = $oldPoints; // Do not increase
            }

            $startNum    = \App\Utils\TicketHelper::parse($existing->range_start);
            $new_end     = \App\Utils\TicketHelper::format($startNum + $points - 1);

            $upserts[] = [
                'event_id'       => $eventId,
                'participant_id' => $entry['participant_id'],
                'month'          => $month,
                'year'           => $year,
                'total_points'   => $points,
                'range_start'    => $existing->range_start,   // keep original start
                'range_end'      => $new_end,
                'status'         => LotteryTicket::STATUS_ACTIVE,
                'description'    => $entry['data']['description'],
                'source'         => $entry['data']['source'],
                'unique_key'     => $entry['unique_key'],
                'created_at'     => $existing->created_at,
                'updated_at'     => $now,
            ];
        }

        if (!empty($upserts)) {
            LotteryTicket::upsert($upserts, ['unique_key'], ['total_points', 'range_start', 'range_end', 'status', 'description', 'updated_at']);
        }

        Log::info(sprintf(
            '✓ Tickets processed: %d new (range %d–%d), %d updated in-place',
            count($newParticipants),
            $startTicketNumber,
            $currentNumber - 1,
            count($existingParticipants)
        ));
    }
}
