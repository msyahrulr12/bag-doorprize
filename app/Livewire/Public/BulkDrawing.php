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
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\WithoutScrolling;

#[WithoutScrolling]
class BulkDrawing extends Component
{
    use WithPagination;
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
    public ?float $processPercentage = 0;
    public bool $isStopping = false;
    public ?int $stopTriggeredAt = null;
    public ?int $totalWinners = 0;
    public bool $isPreview = false;
    public bool $enableRedraw = true;
    public $newWinners = [];
    public $isSingleDrawingMode = false;

    #[Computed]
    public function paginatedWinners()
    {
        return Winner::where('event_prize_id', $this->eventPrize->id)->orderBy('id', 'desc')->paginate(30);
    }

    public function mount($uuid)
    {
        $this->uuid = $uuid;
        $this->eventPrize = EventPrize::with(['event', 'prize'])->where('uuid', $uuid)->firstOrFail();

        // Auto-select active session if exists
        $this->drawSessionId = DrawSession::where('event_id', $this->eventPrize->event_id)
            ->where('status', DrawSession::STATUS_ACTIVE)
            ->where('started_at', '<=', now())
            ->where('ended_at', '>=', now())
            ->first()?->id;

        $this->alreadyConfirmed = (bool) Setting::where('key', 'activate_re_draw_and_confirm')->first()->value == false;

        $this->checkWinner();
    }

    public function updatedPage()
    {
        if (!$this->isPreview) {
            $this->checkWinner();
        }
    }

    private function checkWinner()
    {
        $this->isPreview = false;
        $paginatedWinners = $this->paginatedWinners;

        if ($paginatedWinners->count() > 0) {
            $this->totalWinners = $paginatedWinners->total();
            $this->winners = collect($paginatedWinners->items())->map(fn(Winner $w) => $w->getDataBulk())->split(2)->toArray();
            return true;
        }

        $this->winners = [];
        return false;
    }

    public function draw()
    {
        $this->eventPrize->refresh();
        $remainingQuantity = $this->eventPrize->remaining_quantity;

        if ($remainingQuantity <= 0) {
            $this->dispatch('error', message: 'Prize exhausted.');
            return;
        }

        if (!$this->drawSessionId) {
            $this->dispatch('error', message: 'Please select an active draw session.');
            return;
        }

        $totalWinners = $this->eventPrize?->split_draw > 0 && $this->eventPrize?->split_draw <= $remainingQuantity ? $this->eventPrize->split_draw : $remainingQuantity;

        // Create a batch record
        $batch = \App\Models\BulkDrawBatch::create([
            'event_prize_id' => $this->eventPrize->id,
            'draw_session_id' => $this->drawSessionId,
            'total_winners' => $totalWinners,
            'status' => 'PENDING',
            'created_by' => 'Public User',
        ]);

        $this->batchId = $batch->id;
        $this->batchStatus = 'PENDING';
        $this->totalToProcess = $totalWinners;
        $this->processedCount = 0;
        $this->processPercentage = 0;
        $this->isDrawing = true;
        $this->isStopping = false;
        $this->stopTriggeredAt = null;

        // Dispatch background job
        \App\Jobs\ProcessBulkDrawJob::dispatch($batch->id);

        $this->dispatch('info', message: 'Drawing Started! System is generating ' . $totalWinners . ' winners in the background.');
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

        // Progressive loading: Update table while processing
        if ($batch->total_to_process == 1) {
            $this->isSingleDrawingMode = true;
        }

        if (!$this->isStopping && $batch->results && count($batch->results) > 0) {
            $this->isPreview = true;
            $this->totalWinners = count($batch->results);

            // Show new results combined with existing winners for perspective
            $existingWinners = Winner::where('event_prize_id', $this->eventPrize->id)
                ->where('draw_session_id', $this->drawSessionId)
                ->get()
                ->map(fn(Winner $w) => $w->getDataBulk())
                ->toArray();

            $this->winners = collect(array_merge($batch->results, $existingWinners))->split(2)->toArray();
        }

        $this->processPercentage = $this->totalToProcess > 0 ? ($this->processedCount * 100 / $this->totalToProcess) : 0;

        if ($batch->status === 'COMPLETED' || $batch->status === 'CANCELLED') {


            /* 
            if ($batch->status === 'COMPLETED' && !$this->isStopping && !$this->stopTriggeredAt) {
                $this->stopTriggeredAt = time();
            }
            */

            // If we are in stopping sequence, wait for 100% completion AND the 3s gap
            if ($this->isStopping || $this->stopTriggeredAt) {
                $drawDelay = Setting::where('key', 'draw_delay')->first()->value ?? 3;
                $timeRemaining = $drawDelay - (time() - $this->stopTriggeredAt);
                $isWorkDone = ($this->processedCount >= $this->totalToProcess);

                if ($timeRemaining > 0 || !$isWorkDone) {
                    return; // Keep polling
                }
            }

            $this->batchId = null; // Stop polling
            $this->isStopping = false; // Reset stopping state
            $this->newWinners = $batch->results ?? [];

            $batch->update(['status' => 'COMPLETED']);
            $this->batchStatus = 'COMPLETED';

            if ($this->alreadyConfirmed) {
                $this->confirmWinner();
                $this->isPreview = false;
                $this->checkWinner();
            } else {
                $this->isPreview = true;
                $this->totalWinners = count($this->newWinners);

                // Show all new winners with some oldest winners
                $existingWinners = Winner::where('event_prize_id', $this->eventPrize->id)
                    ->orderBy('id', 'desc')
                    ->limit(20)
                    ->get()
                    ->map(fn(Winner $w) => $w->getDataBulk())
                    ->toArray();

                $combined = array_merge($this->newWinners, $existingWinners);
                $this->winners = collect($combined)->split(2)->toArray();
                $this->isDrawing = false;

                if ($batch->status === 'CANCELLED') {
                    $this->dispatch('info', message: 'Drawing stopped. ' . count($this->newWinners) . ' winners generated.');
                }
            }
        } elseif ($batch->status === 'FAILED') {
            $this->batchId = null;
            $this->isDrawing = false;
            $this->dispatch('error', message: $batch->error_message);
        }
    }

    public function stopDrawing()
    {
        if (!$this->batchId)
            return;

        $batch = \App\Models\BulkDrawBatch::find($this->batchId);
        if ($batch && in_array($batch->status, ['PENDING', 'PROCESSING', 'COMPLETED'])) {
            if ($batch->status !== 'COMPLETED') {
                $batch->update(['status' => 'CANCELLED']);
                $this->batchStatus = 'CANCELLED';
            }

            $this->isStopping = true;
            $this->stopTriggeredAt = time();

            $this->dispatch('info', message: 'Stopping drawing process... Please wait for the reveal.');
        }
    }

    public function confirmWinner()
    {
        // Use newWinners if coming from a batch, otherwise flatten currently displayed winners and filter for un-persisted ones
        $toConfirm = !empty($this->newWinners) ? $this->newWinners : collect($this->winners)->flatten(1)->filter(fn($w) => !isset($w['id']))->toArray();

        if (empty($toConfirm))
            return;

        $this->eventPrize->refresh();
        $count = count($toConfirm);
        if ($this->eventPrize->remaining_quantity < $count) {
            $this->dispatch('error', message: "Remaining quantity ({$this->eventPrize->remaining_quantity}) is less than winners picked.");
            return;
        }

        \DB::beginTransaction();
        try {
            foreach ($toConfirm as $winnerData) {
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
                    'status' => Winner::STATUS_PENDING,
                    'branch_id' => $winnerData['account']['branch']['id'],
                    'branch_code' => $winnerData['account']['branch']['code'],
                    'branch_name' => $winnerData['account']['branch']['branch_name'],
                    'branch_company_book' => $winnerData['account']['branch']['company_book'],
                    'branch_region' => $winnerData['account']['branch']['region'],
                    'account_status' => $winnerData['account']['account_status'],
                ]);

                $this->eventPrize->decrement('remaining_quantity');
            }

            \DB::commit();
            $this->newWinners = [];
            $this->winners = [];
            $this->isPreview = false;
            $this->dispatch('success', message: $count . ' winners confirmed and saved successfully!');
            $this->dispatch('winner-confirmed');
            $this->checkWinner();
        } catch (\Exception $e) {
            \DB::rollBack();
            $this->dispatch('error', message: $e->getMessage());
        }
    }

    public function clearWinner()
    {
        $this->winners = [];
    }
    public function exportCsv()
    {
        return $this->downloadCsv();
    }

    public function exportExcel()
    {
        return $this->downloadCsv('xls');
    }

    protected function downloadCsv($extension = 'csv')
    {
        $exportData = collect($this->winners)->flatten(1);
        if (empty($exportData) || count($exportData) == 0) {
            $exportData = Winner::where('event_prize_id', $this->eventPrize->id)
                ->where('draw_session_id', $this->drawSessionId)
                ->get()
                ->map(fn(Winner $w) => $w->getDataBulk());
        }

        if (empty($exportData) || $exportData->isEmpty()) {
            return null;
        }

        $filename = "winners_" . str_replace([' ', '/', '\\'], '_', $this->eventPrize->prize->prize_name) . "_" . now()->format('Ymd_His') . "." . $extension;

        if ($extension === 'xls') {
            return response()->streamDownload(function () use ($exportData) {
                echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
                <head><meta http-equiv="content-type" content="text/html; charset=utf-8"/></head>
                <body><table border="1">
                    <thead>
                        <tr style="background-color: #f3f4f6;">
                            <th>CIF</th>
                            <th>Account Number</th>
                            <th>Name</th>
                            <th>Region</th>
                            <th>Branch</th>
                            <th>Lucky Number</th>
                            <th>Points</th>
                            <th>Drawn At</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($exportData as $row) {
                    echo '<tr>
                        <td>' . ($row['cif'] ?? '') . '</td>
                        <td>' . ($row['account']['account_number'] ?? $row['account_number'] ?? '') . '</td>
                        <td>' . ($row['name'] ?? '') . '</td>
                        <td>' . ($row['region'] ?? '') . '</td>
                        <td>' . ($row['branch_name'] ?? '') . '</td>
                        <td>' . ($row['lucky_number'] ?? $row['winning_number'] ?? '') . '</td>
                        <td>' . ($row['ticket']['total_points'] ?? 0) . '</td>
                        <td>' . (isset($row['drawn_at']) ? $row['drawn_at'] : now()->format('Y-m-d H:i:s')) . '</td>
                    </tr>';
                }
                echo '</tbody></table></body></html>';
            }, $filename, ['Content-Type' => 'application/vnd.ms-excel']);
        }

        return response()->streamDownload(function () use ($exportData) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ['CIF', 'Account Number', 'Name', 'Region', 'Branch', 'Lucky Number', 'Points', 'Drawn At']);

            foreach ($exportData as $row) {
                fputcsv($file, [
                    $row['cif'] ?? '',
                    $row['account']['account_number'] ?? $row['account_number'] ?? '',
                    $row['name'] ?? '',
                    $row['region'] ?? '',
                    $row['branch_name'] ?? '',
                    $row['lucky_number'] ?? $row['winning_number'] ?? '',
                    $row['ticket']['total_points'] ?? 0,
                    isset($row['drawn_at']) ? $row['drawn_at'] : now()->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        }, $filename);
    }

    public function render()
    {
        return view('livewire.public.bulk-drawing')
            ->layout('layouts.guest');
    }
}
