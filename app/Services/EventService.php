<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventService
{
    public function calculateTickets($event_id)
    {
        // Logic to run background service and return list of accounts
        $event = Event::find($event_id);
    }

    /**
     * Transfer participants and lottery tickets from one event to another.
     */
    public function transferData(int $sourceEventId, int $targetEventId): void
    {
        DB::transaction(function () use ($sourceEventId, $targetEventId) {
            $now = now();

            // 1. Gather all Participant IDs from both main table and pivot table
            $participantIdsFromMain = DB::table('participants')
                ->where('event_id', $sourceEventId)
                ->pluck('id');

            $participantIdsFromPivot = DB::table('event_participant')
                ->where('event_id', $sourceEventId)
                ->pluck('participant_id');

            $participantIds = $participantIdsFromMain->concat($participantIdsFromPivot)->unique();

            Log::info("Transferring " . $participantIds->count() . " participants from event {$sourceEventId} to {$targetEventId}");

            foreach ($participantIds->chunk(1000) as $chunk) {
                $data = $chunk->map(fn($id) => [
                    'event_id' => $targetEventId,
                    'participant_id' => $id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->toArray();
                DB::table('event_participant')->insertOrIgnore($data);

                // Update event_id in main table
                DB::table('participants')
                    ->whereIn('id', $chunk)
                    ->update(['event_id' => $targetEventId, 'updated_at' => $now]);
            }

            // 2. Gather all Lottery Ticket IDs from both main table and pivot table
            $ticketIdsFromMain = DB::table('lottery_tickets')
                ->where('event_id', $sourceEventId)
                ->pluck('id');

            $ticketIdsFromPivot = DB::table('event_lottery_ticket')
                ->where('event_id', $sourceEventId)
                ->pluck('lottery_ticket_id');

            $ticketIds = $ticketIdsFromMain->concat($ticketIdsFromPivot)->unique();

            Log::info("Transferring " . $ticketIds->count() . " lottery tickets from event {$sourceEventId} to {$targetEventId}");

            foreach ($ticketIds->chunk(1000) as $chunk) {
                $data = $chunk->map(fn($id) => [
                    'event_id' => $targetEventId,
                    'lottery_ticket_id' => $id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->toArray();
                DB::table('event_lottery_ticket')->insertOrIgnore($data);

                // Update event_id in main table
                DB::table('lottery_tickets')
                    ->whereIn('id', $chunk)
                    ->update(['event_id' => $targetEventId, 'updated_at' => $now]);
            }

            // 3. Update Target Event's last_ticket_number to reflect the transferred tickets
            $maxTicket = DB::table('lottery_tickets')
                ->where('event_id', $targetEventId)
                ->max('range_end') ?? 0;

            DB::table('events')
                ->where('id', $targetEventId)
                ->update([
                    'last_ticket_number' => $maxTicket,
                    'updated_at' => $now
                ]);
        });
    }
}