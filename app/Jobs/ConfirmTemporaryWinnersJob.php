<?php

namespace App\Jobs;

use App\Models\TemporaryWinner;
use App\Models\Winner;
use App\Models\Prize;
use App\Models\EventPrize;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfirmTemporaryWinnersJob implements ShouldQueue
{
    use Queueable;

    protected $drawSessionId;

    /**
     * Create a new job instance.
     */
    public function __construct($drawSessionId)
    {
        $this->drawSessionId = $drawSessionId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tempWinners = TemporaryWinner::where('draw_session_id', $this->drawSessionId)->get();

        if ($tempWinners->isEmpty()) {
            return;
        }

        DB::beginTransaction();
        try {
            foreach ($tempWinners as $tw) {
                $eventPrize = EventPrize::with(['event', 'prize'])->find($tw->event_prize_id);

                if (!$eventPrize || $eventPrize->remaining_quantity <= 0) {
                    Log::warning("Skipping confirmation for TemporaryWinner ID: {$tw->id}. Prize exhausted or not found.");
                    continue;
                }

                $wData = $tw->toArray();
                unset($wData['id'], $wData['created_at'], $wData['updated_at'], $wData['deleted_at']);

                $fullData = array_merge($wData, [
                    'prize_name' => $eventPrize->prize->prize_name,
                    'prize_tier' => Prize::PRIZE_TIER[$eventPrize->prize->tier] ?? 'Common',
                    'prize_total_quantity' => $eventPrize->total_quantity,
                    'prize_value' => $eventPrize->prize->value,
                    'prize_description' => $eventPrize->prize->description,
                    'event_code' => $eventPrize->event->event_code,
                    'event_name' => $eventPrize->event->event_name,
                ]);

                Winner::create($fullData);
                $eventPrize->decrement('remaining_quantity');
                $tw->delete();
            }
            DB::commit();
            Log::info("Successfully confirmed winners for Draw Session ID: {$this->drawSessionId}");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to confirm winners for Draw Session ID: {$this->drawSessionId}. Error: " . $e->getMessage());
        }
    }
}
