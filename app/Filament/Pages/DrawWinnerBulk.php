<?php

namespace App\Filament\Pages;

use App\Models\DrawSession;
use App\Models\EventPrize;
use App\Models\LotteryTicket;
use App\Models\Prize;
use App\Models\Setting;
use App\Models\Winner;
use Auth;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DrawWinnerBulk extends Page
{
    use InteractsWithForms;
    protected string $view = 'filament.pages.draw-winner-bulk';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Gift;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'draw-winner-bulk/{eventPrize}';

    public ?int $batchId = null;
    public ?string $batchStatus = null;
    public ?int $processedCount = 0;
    public ?int $totalToProcess = 0;

    public ?int $eventPrizeId = null;
    public ?EventPrize $eventPrize = null;

    public ?array $searchData = [];
    public ?array $winners = null;
    public ?bool $alreadyConfirmed = false;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label(__('Go Back'))
                ->url(route('filament.admin.resources.events.index'))
                ->color('gray')
                ->button()
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    public function mount($eventPrize)
    {
        $this->eventPrizeId = $eventPrize->id;
        if (!$this->eventPrizeId) {
            abort(404);
        }

        $this->eventPrize = EventPrize::with(['event', 'prize'])->findOrFail($this->eventPrizeId);

        $defaultSession = DrawSession::where('event_id', $this->eventPrize->event_id)
            ->where('status', 'ACTIVE')
            ->first();

        $this->form->fill([
            'draw_session_id' => $defaultSession?->id
        ]);

        $winners = Winner::where('event_prize_id', $this->eventPrize->id)->where('status', 'PENDING')->get();
        if ($winners->count() > 0) {
            $this->alreadyConfirmed = true;
            $this->winners = $winners->map(function ($winner) {
                return $winner->getDataBulk();
            })->toArray();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('draw_session_id')
                    ->label('Draw Session')
                    ->options(DrawSession::where('event_id', $this->eventPrize->event_id)->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->default(fn() => DrawSession::where('event_id', $this->eventPrize->event_id)->where('status', 'ACTIVE')->first()?->id),
            ])
            ->statePath('searchData');
    }

    public function draw()
    {
        $data = $this->form->getState();
        $this->eventPrize->refresh();
        $remainingQuantity = $this->eventPrize->remaining_quantity;

        if ($remainingQuantity <= 0) {
            Notification::make()->danger()->title('No items left')->send();
            return;
        }

        // Create a batch record
        $batch = \App\Models\BulkDrawBatch::create([
            'event_prize_id' => $this->eventPrize->id,
            'draw_session_id' => $data['draw_session_id'],
            'total_winners' => $remainingQuantity,
            'status' => 'PENDING',
            'created_by' => Auth::user()->name,
        ]);

        $this->batchId = $batch->id;
        $this->batchStatus = 'PENDING';
        $this->totalToProcess = $remainingQuantity;
        $this->processedCount = 0;

        // Dispatch background job
        \App\Jobs\ProcessBulkDrawJob::dispatch($batch->id);

        Notification::make()
            ->info()
            ->title('Drawing Started')
            ->body("System is generating {$remainingQuantity} winners in the background. Please wait...")
            ->send();
    }

    public function checkBatchStatus()
    {
        if (!$this->batchId)
            return;

        $batch = \App\Models\BulkDrawBatch::find($this->batchId);
        if (!$batch)
            return;

        $this->batchStatus = $batch->status;
        $this->processedCount = $batch->processed_winners;

        if ($batch->status === 'COMPLETED') {
            $this->winners = $batch->results;
            $this->batchId = null; // Stop polling

            Notification::make()
                ->success()
                ->title('Winners Generated')
                ->body(count($this->winners) . " winners are ready for confirmation.")
                ->send();
        } elseif ($batch->status === 'FAILED') {
            $this->batchId = null; // Stop polling
            Notification::make()
                ->danger()
                ->title('Drawing Failed')
                ->body($batch->error_message)
                ->send();
        }
    }

    public function clearWinner()
    {
        $this->winners = null;
        $this->batchId = null;
        $this->batchStatus = null;
    }

    public function confirmWinner()
    {
        if (!$this->winners)
            return;

        // Re-check availability
        $this->eventPrize->refresh();
        $count = count($this->winners);
        if ($this->eventPrize->remaining_quantity < $count) {
            Notification::make()
                ->danger()
                ->title('Prize Quantity Mismatch')
                ->body("Sorry, the remaining quantity ({$this->eventPrize->remaining_quantity}) is less than the number of winners picked ({$count}). Please re-draw.")
                ->send();
            return;
        }

        \DB::beginTransaction();
        try {
            foreach ($this->winners as $winnerData) {
                // Re-check customer eligibility one last time
                $customer_id = $winnerData['customer']['id'];
                $alreadyWon = Winner::whereHas('eventPrize', fn($q) => $q->where('event_prizes.event_id', $this->eventPrize->event_id))
                    ->whereHas('participant.account', fn($q) => $q->where('customer_id', $customer_id))
                    ->exists();

                if ($alreadyWon) {
                    throw new \Exception("Customer " . $winnerData['name'] . " has already won in this event.");
                }

                $ticket = $winnerData['ticket'];
                $participant = $winnerData['participant'];

                // Create Winner record
                Winner::create([
                    'participant_id' => $participant['id'],
                    'participant_cif' => $participant['participant_cif'],
                    'participant_account_number' => $participant['participant_account_number'] ?? ($winnerData['account']['account_number'] ?? 'N/A'),
                    'participant_name' => $participant['participant_name'],
                    'participant_email' => $participant['participant_email'] ?? null,
                    'participant_phone_number' => $participant['participant_phone_number'] ?? null,
                    'event_prize_id' => $this->eventPrize->id,
                    'prize_name' => $this->eventPrize->prize->prize_name,
                    'prize_tier' => Prize::PRIZE_TIER[$this->eventPrize->prize->tier] ?? 'Common',
                    'prize_total_quantity' => $this->eventPrize->total_quantity,
                    'prize_value' => $this->eventPrize->prize->value,
                    'prize_description' => $this->eventPrize->prize->description,
                    'event_code' => $this->eventPrize->event->event_code,
                    'event_name' => $this->eventPrize->event->event_name,
                    'draw_session_id' => $winnerData['draw_session_id'] ?? $batch->draw_session_id ?? $this->searchData['draw_session_id'],
                    'winning_number' => $winnerData['lucky_number'],
                    'drawn_at' => now(),
                    'drawn_by' => Auth::user()->name ?? 'System',
                    'lottery_ticket_id' => $ticket['id'],
                    'total_points' => $ticket['total_points'],
                    'range_start' => $ticket['range_start'],
                    'range_end' => $ticket['range_end'],
                    'status' => 'PENDING',
                ]);

                // Reduce remaining quantity
                $this->eventPrize->decrement('remaining_quantity');
            }

            \DB::commit();

            Notification::make()
                ->success()
                ->title('Winners Confirmed!')
                ->body(count($this->winners) . " winners have been recorded.")
                ->send();

            $this->winners = null;
        } catch (\Exception $e) {
            \DB::rollBack();
            Notification::make()
                ->danger()
                ->title('Conflict Detected')
                ->body($e->getMessage())
                ->send();
            $this->winners = null;
        }
    }
}
