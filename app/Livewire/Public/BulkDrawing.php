<?php

namespace App\Livewire\Public;

use App\Models\DrawSession;
use App\Models\EventPrize;
use App\Models\LotteryTicket;
use App\Models\Setting;
use App\Models\Winner;
use App\Models\Prize;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BulkDrawing extends Component
{
    public $uuid;
    public $eventPrize;
    public $drawSessionId;
    public $winners = [];
    public $isDrawing = false;

    public $batchId = null;
    public $batchStatus = null;
    public $processedCount = 0;
    public $totalToProcess = 0;
    public $alreadyConfirmed = false;

    public function mount($uuid)
    {
        $this->uuid = $uuid;
        $this->eventPrize = EventPrize::with(['event', 'prize'])->where('uuid', $uuid)->firstOrFail();

        // Auto-select active session if exists
        $this->drawSessionId = DrawSession::where('event_id', $this->eventPrize->event_id)
            ->where('status', 'ACTIVE')
            ->first()?->id;

        $winners = Winner::where('event_prize_id', $this->eventPrize->id)->where('status', 'PENDING')->get();
        if ($winners->count() > 0) {
            $this->alreadyConfirmed = true;
            $this->winners = $winners->map(function ($winner) {
                return $winner->getDataBulk();
            });
        }
    }

    public function draw()
    {
        if (!$this->drawSessionId) {
            $this->dispatch('error', message: 'No active draw session selected.');
            return;
        }

        $this->eventPrize->refresh();
        $remainingQuantity = $this->eventPrize->remaining_quantity;

        if ($remainingQuantity <= 0) {
            $this->dispatch('error', message: 'No items left.');
            return;
        }

        // Create a batch record
        $batch = \App\Models\BulkDrawBatch::create([
            'event_prize_id' => $this->eventPrize->id,
            'draw_session_id' => $this->drawSessionId,
            'total_winners' => $remainingQuantity,
            'status' => 'PENDING',
            'created_by' => 'Public User',
        ]);

        $this->batchId = $batch->id;
        $this->batchStatus = 'PENDING';
        $this->totalToProcess = $remainingQuantity;
        $this->processedCount = 0;
        $this->isDrawing = true;

        // Dispatch background job
        \App\Jobs\ProcessBulkDrawJob::dispatch($batch->id);
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
            $this->batchId = null;
            $this->isDrawing = false;
        } elseif ($batch->status === 'FAILED') {
            $this->batchId = null;
            $this->isDrawing = false;
            $this->dispatch('error', message: $batch->error_message);
        }
    }

    public function confirmWinner()
    {
        if (empty($this->winners))
            return;

        $this->eventPrize->refresh();
        $count = count($this->winners);
        if ($this->eventPrize->remaining_quantity < $count) {
            $this->dispatch('error', message: "Remaining quantity ({$this->eventPrize->remaining_quantity}) is less than winners picked.");
            return;
        }

        \DB::beginTransaction();
        try {
            foreach ($this->winners as $winnerData) {
                $customer_id = $winnerData['customer']['id'];
                $alreadyWon = Winner::whereHas('eventPrize', fn($q) => $q->where('event_prizes.event_id', $this->eventPrize->event_id))
                    ->whereHas('participant.account', fn($q) => $q->where('customer_id', $customer_id))
                    ->exists();

                if ($alreadyWon) {
                    throw new \Exception("Customer " . $winnerData['name'] . " has already won.");
                }

                Winner::create([
                    'participant_id' => $winnerData['participant']['id'],
                    'participant_cif' => $winnerData['participant']['participant_cif'],
                    'participant_account_number' => $winnerData['account']['account_number'],
                    'participant_name' => $winnerData['participant']['participant_name'],
                    'participant_email' => $winnerData['participant']['participant_email'],
                    'participant_phone_number' => $winnerData['participant']['participant_phone_number'] ?? 'N/A',
                    'event_prize_id' => $this->eventPrize->id,
                    'prize_name' => $this->eventPrize->prize->prize_name,
                    'prize_tier' => Prize::PRIZE_TIER[$this->eventPrize->prize->tier] ?? 'Common',
                    'prize_total_quantity' => $this->eventPrize->total_quantity,
                    'prize_value' => $this->eventPrize->prize->value,
                    'prize_description' => $this->eventPrize->prize->description,
                    'event_code' => $this->eventPrize->event->event_code,
                    'event_name' => $this->eventPrize->event->event_name,
                    'draw_session_id' => $this->drawSessionId,
                    'winning_number' => $winnerData['lucky_number'],
                    'drawn_at' => now(),
                    'drawn_by' => Auth::user()->name ?? 'Guest User',
                    'lottery_ticket_id' => $winnerData['ticket']['id'],
                    'total_points' => $winnerData['ticket']['total_points'],
                    'range_start' => $winnerData['ticket']['range_start'],
                    'range_end' => $winnerData['ticket']['range_end'],
                    'status' => 'PENDING',
                ]);

                $this->eventPrize->decrement('remaining_quantity');
            }

            \DB::commit();
            $this->winners = [];
            $this->dispatch('winner-confirmed');
        } catch (\Exception $e) {
            \DB::rollBack();
            $this->dispatch('error', message: $e->getMessage());
        }
    }

    public function clearWinner()
    {
        $this->winners = [];
    }

    public function render()
    {
        return view('livewire.public.bulk-drawing')
            ->layout('layouts.guest');
    }
}
