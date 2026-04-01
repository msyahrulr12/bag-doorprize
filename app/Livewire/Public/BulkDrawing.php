<?php

namespace App\Livewire\Public;

use App\Models\DrawSession;
use App\Models\EventPrize;
use App\Models\LotteryTicket;
use App\Models\Setting;
use App\Models\Winner;
use App\Models\TemporaryWinner;
use App\Models\Prize;
use App\Models\BulkDrawBatch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
    public bool $isReadyToReveal = false;

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
        
        // Always check if there are temporary winners first
        $tempWinners = TemporaryWinner::where('draw_session_id', $this->drawSessionId)
            ->where('event_prize_id', $this->eventPrize->id)
            ->get();

        if ($tempWinners->count() > 0) {
            $this->newWinners = $tempWinners->map(fn($tw) => $tw->getData())->toArray();
            $this->isPreview = true;
            $this->totalWinners = count($this->newWinners);
            
            // For batch view, show new winners split into 3 columns
            $this->winners = collect($this->newWinners)->split(3)->toArray();
            return true;
        }

        $paginatedWinners = $this->paginatedWinners;

        if ($paginatedWinners->count() > 0) {
            $this->totalWinners = $paginatedWinners->total();
            $this->winners = collect($paginatedWinners->items())->map(fn(Winner $w) => $w->getDataBulk())->split(3)->toArray();
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
        
        // If there are already temporary winners and redraw is allowed, we should reset first or redraw
        // For simplicity, we auto-delete temporary winners for this prize session if starting a new draw
        TemporaryWinner::where('draw_session_id', $this->drawSessionId)
            ->where('event_prize_id', $this->eventPrize->id)
            ->delete();

        $totalWinners = $this->eventPrize?->split_draw > 0 && $this->eventPrize?->split_draw <= $remainingQuantity ? $this->eventPrize->split_draw : $remainingQuantity;

        // Create a batch record
        $batch = BulkDrawBatch::create([
            'event_prize_id' => $this->eventPrize->id,
            'draw_session_id' => $this->drawSessionId,
            'total_winners' => $totalWinners,
            'status' => 'PENDING',
            'created_by' => Auth::user()->name ?? 'Public Guest',
        ]);

        $this->batchId = $batch->id;
        $this->batchStatus = 'PENDING';
        $this->totalToProcess = $totalWinners;
        $this->processedCount = 0;
        $this->processPercentage = 0;
        $this->isDrawing = true;
        $this->isStopping = false;
        $this->stopTriggeredAt = null;
        $this->isReadyToReveal = false;

        // Dispatch background job
        \App\Jobs\ProcessBulkDrawJob::dispatch($batch->id);

        $this->dispatch('info', message: 'Drawing Started! System is generating ' . $totalWinners . ' winners.');
    }

    public function checkBatchStatus()
    {
        if (!$this->batchId) return;

        $batch = BulkDrawBatch::find($this->batchId);
        if (!$batch) return;

        $this->batchStatus = $batch->status;
        $this->processedCount = $batch->processed_winners;
        $this->processPercentage = $this->totalToProcess > 0 ? ($this->processedCount * 100 / $this->totalToProcess) : 0;

        if (in_array($batch->status, ['COMPLETED', 'CANCELLED', 'FAILED'])) {
            $this->isReadyToReveal = true;
            if ($this->isStopping) {
                $this->finalizeResults($batch);
            }
        }
    }

    public function stopDrawing()
    {
        if (!$this->batchId) {
            $this->isDrawing = false;
            return;
        }

        $this->isStopping = true;
        $this->stopTriggeredAt = time();

        $batch = BulkDrawBatch::find($this->batchId);
        if ($batch && in_array($batch->status, ['COMPLETED', 'CANCELLED', 'FAILED'])) {
            $this->finalizeResults($batch);
        } else {
            if ($batch) {
                $batch->update(['status' => 'CANCELLED']);
            }
            $this->dispatch('info', message: 'Finalizing current winners... Please wait.');
        }
    }

    private function finalizeResults($batch)
    {
        $this->batchId = null; 
        $this->isStopping = false;
        $this->isDrawing = false;
        
        $this->checkWinner();

        if ($this->alreadyConfirmed) {
            $this->confirmWinner();
        }
    }

    public function confirmWinners()
    {
        $tempWinners = TemporaryWinner::where('draw_session_id', $this->drawSessionId)
            ->where('event_prize_id', $this->eventPrize->id)
            ->get();

        if ($tempWinners->isEmpty()) return;

        DB::beginTransaction();
        try {
            foreach ($tempWinners as $tw) {
                $this->eventPrize->refresh();
                if ($this->eventPrize->remaining_quantity <= 0) {
                     throw new \Exception("Prize quantity exhausted.");
                }

                $wData = $tw->toArray();
                unset($wData['id'], $wData['created_at'], $wData['updated_at'], $wData['deleted_at']);
                
                $fullData = array_merge($wData, [
                    'prize_name' => $this->eventPrize->prize->prize_name,
                    'prize_tier' => Prize::PRIZE_TIER[$this->eventPrize->prize->tier] ?? 'Common',
                    'prize_total_quantity' => $this->eventPrize->total_quantity,
                    'prize_value' => $this->eventPrize->prize->value,
                    'prize_description' => $this->eventPrize->prize->description,
                    'event_code' => $this->eventPrize->event->event_code,
                    'event_name' => $this->eventPrize->event->event_name,
                ]);

                Winner::create($fullData);
                $this->eventPrize->decrement('remaining_quantity');
                $tw->delete();
            }
            DB::commit();

            $this->dispatch('success', message: 'All winners have been confirmed!');
            $this->dispatch('winner-confirmed');
            $this->isPreview = false;
            $this->newWinners = [];
            $this->checkWinner();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Confirmation failed: ' . $e->getMessage());
        }
    }

    public function resetWinners()
    {
        TemporaryWinner::where('draw_session_id', $this->drawSessionId)
            ->where('event_prize_id', $this->eventPrize->id)
            ->delete();

        $this->isPreview = false;
        $this->newWinners = [];
        $this->checkWinner();

        $this->dispatch('success', message: 'Winners have been reset. You can draw again.');
    }

    public function confirmWinner()
    {
        $this->confirmWinners();
    }

    public function clearWinner()
    {
        $this->newWinners = [];
        $this->winners = [];
        $this->isPreview = false;
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
                            <th>CIF</th><th>Account Number</th><th>Name</th><th>Region</th><th>Branch</th><th>Lucky Number</th><th>Points</th><th>Drawn At</th>
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
