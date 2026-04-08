<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\Participant;
use App\Services\EventService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateEvent extends CreateRecord
{
    use \App\Traits\InteractsWithApprovals;

    protected static string $resource = EventResource::class;

    protected function afterCreate(): void
    {
        Log::info("After create event");
        try {
            DB::transaction(function() {
                $status = $this->record->status;
                if ($status == Event::STATUS_ACTIVE) {
                    $participantEventId = Participant::first()?->event_id;

                    // 1. Set active events without participants to INACTIVE
                    Event::where('status', Event::STATUS_ACTIVE)
                        ->whereNotIn('id', [$this->record->id, $participantEventId])
                        ->whereDoesntHave('participants')
                        ->update(['status' => Event::STATUS_INACTIVE]);

                    if ($participantEventId && $participantEventId != $this->record->id) {
                        $previouslyActiveEvent = Event::find($participantEventId);

                        if ($previouslyActiveEvent) {
                            $previouslyActiveEvent->update(['status' => Event::STATUS_COMPLETED]);
                            
                            Log::info("Transfer data from event {$previouslyActiveEvent->id} to new event {$this->record->id}");
                            $eventService = new EventService();
                            $eventService->transferData($previouslyActiveEvent->id, $this->record->id);
                        }
                    }
                }
            });
        } catch (\Throwable $th) {
            Log::error("Error transfer data: " . $th->getMessage(), $th->getTrace());
        }
    }
}