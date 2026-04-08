<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\Participant;
use App\Services\EventService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EditEvent extends EditRecord
{
    use \App\Traits\InteractsWithApprovals;

    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
            ViewAction::make(),
            Action::make('List Events')
                ->url(ListEvents::getUrl())
                ->color('gray')
        ];
    }

    protected function afterSave(): void
    {
        try {
            DB::transaction(function () {
                $status = $this->record->status;

                if ($status == Event::STATUS_ACTIVE) {
                    $participantEventId = Participant::first()->event_id;

                    // 1. Set other active events without participants to INACTIVE
                    Event::where('status', Event::STATUS_ACTIVE)
                        ->whereNotIn('id', [$this->record->id, $participantEventId])
                        ->whereDoesntHave('participants')
                        ->update(['status' => Event::STATUS_INACTIVE]);

                    if ($participantEventId) {
                        $previouslyActiveEvents = Event::where('id', $participantEventId)
                            ->first();

                        $previouslyActiveEvents->update(['status' => Event::STATUS_COMPLETED]);

                        $eventService = new EventService();
                        $eventService->transferData($previouslyActiveEvents->id, $this->record->id);
                    }
                }
            });
        } catch (\Throwable $th) {
            Log::error("Error transfer data in EditEvent: " . $th->getMessage(), $th->getTrace());
        }
    }
}
