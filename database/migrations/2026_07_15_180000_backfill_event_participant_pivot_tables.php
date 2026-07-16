<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        // 1. Backfill event_participant pivot table
        DB::table('participants')
            ->whereNotNull('event_id')
            ->select('id', 'event_id', 'id as participant_id')
            ->chunkById(1000, function ($rows) use ($now) {
                $data = $rows->map(function ($row) use ($now) {
                    return [
                        'event_id' => $row->event_id,
                        'participant_id' => $row->participant_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->toArray();
                DB::table('event_participant')->insertOrIgnore($data);
            }, 'id');

        // 2. Backfill event_lottery_ticket pivot table
        DB::table('lottery_tickets')
            ->whereNotNull('event_id')
            ->select('id', 'event_id', 'id as lottery_ticket_id')
            ->chunkById(1000, function ($rows) use ($now) {
                $data = $rows->map(function ($row) use ($now) {
                    return [
                        'event_id' => $row->event_id,
                        'lottery_ticket_id' => $row->lottery_ticket_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->toArray();
                DB::table('event_lottery_ticket')->insertOrIgnore($data);
            }, 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback action needed for backfilling
    }
};
