<?php

namespace App\Livewire\Public;

use App\Models\DrawSession;
use App\Models\EventPrize;
use App\Models\LotteryTicket;
use App\Models\Setting;
use App\Models\Winner;
use App\Models\TemporaryWinner;
use App\Models\Participant;
use App\Models\Prize;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use App\Models\BulkDrawBatch;
use Livewire\Attributes\Layout;
use Livewire\Attributes\WithoutScrolling;

#[WithoutScrolling]
class GrandDrawing extends Component
{
    use WithPagination;
    public $uuid;
    public $eventPrize;
    public $drawSessionId;
    public $winners = [];
    public $isDrawing = false;
    public $pendingWinners = []; // Array of winners being drawn
    public $pendingWinner = null; // Still keep for single compatibility or first item
    public bool $enableRedraw = false;
    public bool $isPreview = false;
    public $winner = null;
    public $candidates = [];

    public int $totalDataToProcess = 0;
    public array $randomData = [
        'names' => [],
        'branches' => [],
        'lucky_numbers' => [],
    ];

    // Bulk progress properties
    public $batchId;
    public $batchStatus;
    public $totalToProcess = 0;
    public $processedCount = 0;
    public $processPercentage = 0;
    public $isStopping = false;
    public $stopTriggeredAt = null;
    public $isReadyToReveal = false;

    #[Computed]
    public function availableQuantity()
    {
        $this->eventPrize->refresh();
        $stagedCount = TemporaryWinner::where('event_prize_id', $this->eventPrize->id)
            ->where('draw_session_id', $this->drawSessionId)
            ->count();
        
        return max(0, $this->eventPrize->remaining_quantity - $stagedCount);
    }

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

        $this->enableRedraw = (bool) Setting::where('key', 'activate_re_draw_and_confirm')->first()->value ?? true;

        $this->updateTotalDataToProcess();
        $this->checkWinner();
    }

    private function updateTotalDataToProcess()
    {
        $this->eventPrize->refresh();
        $this->totalDataToProcess = $this->eventPrize?->remaining_quantity && $this->eventPrize?->remaining_quantity < $this->eventPrize?->split_draw ? $this->eventPrize?->remaining_quantity : $this->eventPrize?->split_draw;

        $randomParticipants = $this->eventPrize->event->randomParticipants->map(function ($p) {
            return [
                'id' => $p->id,
                'participant_name' => $p->participant_name,
                'participant_branch' => $p->account->branch->branch_name,
                'participant_lucky_number' => $p?->lotteryTickets?->first()?->range_start ?? 'A' . rand(10000, 99999),
            ];
        });

        $this->randomData = [
            'names' => $randomParticipants->pluck('participant_name')->toArray(),
            'branches' => $randomParticipants->pluck('participant_branch')->toArray(),
            'lucky_numbers' => $randomParticipants->pluck('participant_lucky_number')->toArray(),
        ];
    }

    public function updatedPage()
    {
        if (!$this->isPreview) {
            $this->checkWinner();
        }
    }

    private function checkWinner()
    {
        if ($this->isDrawing) {
            return false;
        }
        $this->isPreview = false;

        if ($this->drawSessionId) {
            // Always check if there are temporary winners first
            $tempWinners = TemporaryWinner::where('draw_session_id', $this->drawSessionId)
                ->where('event_prize_id', $this->eventPrize->id)
                ->get();

            if ($tempWinners->count() > 0) {
                $this->pendingWinners = $tempWinners->map(fn($tw) => $tw->getData())->toArray();
                $this->winner = end($this->pendingWinners);
                $this->isPreview = true;

                // For the preview table display
                $this->winners = collect($this->pendingWinners)->split(3)->toArray();
            }
        }

        $paginatedWinners = $this->paginatedWinners();

        if ($paginatedWinners->count() > 0) {
            $firstWinner = $paginatedWinners->first();

            if (!$this->isPreview) {
                $firstWinner->load(['participant.account.branch', 'participant.account.customer']);
                $this->winner = [
                    'id' => $firstWinner->id,
                    'ticket' => $firstWinner->lotteryTicket?->toArray() ?? [],
                    'participant' => $firstWinner->participant?->toArray() ?? [],
                    'customer' => $firstWinner->participant?->account?->customer?->toArray() ?? [],
                    'lucky_number' => $firstWinner->winning_number,
                    'winning_number' => $firstWinner->range_start === $firstWinner->range_end
                        ? $firstWinner->range_start
                        : "{$firstWinner->range_start} - {$firstWinner->range_end}",
                    'draw_session_id' => $firstWinner->draw_session_id,
                    'branch_name' => $firstWinner->branch_name,
                    'region' => $firstWinner->branch_region,
                    'drawn_at' => $firstWinner->created_at->format('Y-m-d H:i:s'),
                ];
            }

            // For the table display (confirmed ones below)
            if (!$this->isPreview) {
                $this->winners = collect($paginatedWinners->items())->map(fn(Winner $w) => $w->getDataBulk())->split(3)->toArray();
            }

            return true;
        }

        if (!$this->isPreview) {
            $this->winners = [];
            $this->winner = null;
        }
        return false;
    }

    public function startDrawing()
    {
        $this->eventPrize->refresh();
        $remainingQuantity = (int)$this->eventPrize->remaining_quantity;
        
        if ($remainingQuantity <= 0) {
            $this->dispatch('error', message: 'Prize exhausted.');
            return;
        }

        if (!$this->drawSessionId) {
            $this->dispatch('error', message: 'No active draw session selected.');
            return;
        }

        // Defensive cleanup of any stale temporary winners for this prize/session
        // TemporaryWinner::where('draw_session_id', $this->drawSessionId)
        //     ->where('event_prize_id', $this->eventPrize->id)
        //     ->delete();

        $totalToDraw = $this->eventPrize->split_draw > 0 && $this->eventPrize->split_draw <= $remainingQuantity ? $this->eventPrize->split_draw : $remainingQuantity;

        // Create a batch record (Same as BulkDraw)
        $batch = BulkDrawBatch::create([
            'event_prize_id' => $this->eventPrize->id,
            'draw_session_id' => $this->drawSessionId,
            'total_winners' => $totalToDraw,
            'status' => 'PENDING',
            'created_by' => Auth::user()->name ?? 'Public Guest',
        ]);

        $this->batchId = $batch->id;
        $this->batchStatus = 'PENDING';
        $this->totalToProcess = $totalToDraw;
        $this->processedCount = 0;
        $this->processPercentage = 0;
        $this->isDrawing = true;
        $this->isStopping = false;
        $this->stopTriggeredAt = null;
        $this->isReadyToReveal = false;

        $this->winners = [];
        $this->winner = null;
        $this->pendingWinners = [];
        $this->candidates = [];
        $this->isPreview = false;

        // Dispatch background job
        \App\Jobs\ProcessBulkDrawJob::dispatch($batch->id);

        $this->dispatch('trigger-animation');
    }

    public function checkBatchStatus()
    {
        if (!$this->batchId) return;

        $batch = BulkDrawBatch::find($this->batchId);
        if (!$batch) return;

        $this->batchStatus = $batch->status;
        $this->processedCount = $batch->processed_winners;
        $this->processPercentage = $batch->total_winners > 0 ? ($batch->processed_winners / $batch->total_winners) * 100 : 0;

        if (in_array($batch->status, ['COMPLETED', 'CANCELLED', 'FAILED'])) {
            $this->isReadyToReveal = true;
            // For Grand Drawing, we still wait for the user to click STOP DRAWING
            // but we can pre-fetch candidates if needed.
            if ($batch->status === 'COMPLETED' && empty($this->candidates)) {
                $candidateTickets = LotteryTicket::query()
                    ->where('event_id', $this->eventPrize->event_id)
                    ->where('total_points', '>=', $this->eventPrize->min_points_required)
                    ->where('status', LotteryTicket::STATUS_ACTIVE)
                    ->inRandomOrder()
                    ->limit(20)
                    ->get();
                $this->candidates = $candidateTickets->map(fn($t) => $this->generateLuckyNumber($t))->toArray();
            }
        }
    }

    public function stopDrawing()
    {
        if (!$this->isDrawing) return;
        
        if ($this->batchStatus === 'PROCESSING' || $this->batchStatus === 'PENDING') {
            $batch = BulkDrawBatch::find($this->batchId);
            if ($batch) {
                $batch->update(['status' => 'CANCELLED']);
            }
        }
        
        $this->isStopping = true;
        $this->finalizeResults();
    }

    private function finalizeResults()
    {
        $batch = BulkDrawBatch::find($this->batchId);
        
        $this->batchId = null;
        $this->isStopping = false;
        $this->isDrawing =   false;
        $this->isReadyToReveal = false;
        
        $this->checkWinner();
    }

    public function performDraw()
    {
        // This is now handled by the background job
        return true;
    }

    public function finishDrawing()
    {
        $this->isDrawing = false;
        $this->isPreview = true;
        $this->updateTotalDataToProcess();
        $this->checkWinner();

        if (empty($this->pendingWinners)) {
            $this->dispatch('error', message: 'Winner data not found. Animation stopped.');
            return;
        }

        if (!$this->enableRedraw) {
            $this->confirmWinners();
            $this->dispatch('success', message: 'Winner confirmed and saved successfully!');
        } else {
            $this->dispatch('success', message: 'Winners have been picked and staged for review.');
        }
    }

    public function confirmWinners()
    {
        $tempWinners = TemporaryWinner::where('draw_session_id', $this->drawSessionId)
            ->where('event_prize_id', $this->eventPrize->id)
            ->get();

        if ($tempWinners->isEmpty())
            return;

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
                // $tw->delete();
            }
            DB::commit();

            $this->dispatch('success', message: 'All winners have been confirmed!');
            $this->dispatch('winner-confirmed');
            $this->winner = null;
            $this->isPreview = false;
            $this->pendingWinners = [];
            $this->updateTotalDataToProcess();
            $this->checkWinner();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Confirmation failed: ' . $e->getMessage());
        }
    }

    public function resetWinners()
    {
        DB::beginTransaction();
        try {
            $this->isDrawing = false;
            $this->isStopping = false;
            $this->isReadyToReveal = false;
            $this->batchId = null;

            // Delete Temporary Winners
            TemporaryWinner::where('draw_session_id', $this->drawSessionId)
                ->where('event_prize_id', $this->eventPrize->id)
                ->delete();

            // Delete Confirmed Winners for this session and prize
            // Only if session is still considered active/open in the component
            if ($this->drawSessionId) {
                $confirmedWinners = Winner::where('draw_session_id', $this->drawSessionId)
                    ->where('event_prize_id', $this->eventPrize->id)
                    ->get();

                foreach ($confirmedWinners as $w) {
                    $this->eventPrize->refresh();
                    $this->eventPrize->increment('remaining_quantity');
                    $w->delete();
                }
            }

            DB::commit();

            $this->winner = null;
            $this->winners = [];
            $this->pendingWinners = [];
            $this->candidates = [];
            $this->isPreview = false;
            $this->updateTotalDataToProcess();
            $this->checkWinner();

            $this->dispatch('success', message: 'Winners have been reset. You can draw again.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Reset failed: ' . $e->getMessage());
        }
    }

    public function confirmWinner()
    {
        $this->confirmWinners();
    }

    private function generateLuckyNumber($ticket): string
    {
        $start = $ticket->range_start;
        $end = $ticket->range_end;

        if ($start === $end)
            return $start;

        if (
            preg_match('/^([A-Z]*)(\d+)$/', $start, $startMatch) &&
            preg_match('/^([A-Z]*)(\d+)$/', $end, $endMatch)
        ) {
            $prefix = $startMatch[1];
            $startNum = (int) $startMatch[2];
            $endNum = (int) $endMatch[2];
            $randomNum = mt_rand($startNum, $endNum);
            return $prefix . str_pad($randomNum, strlen($startMatch[2]), '0', STR_PAD_LEFT);
        }

        return $start;
    }

    private function findWinnerInRegion(?string $region, int $eventId, array $excludeCustomerIds = []): ?LotteryTicket
    {
        $query = LotteryTicket::query()
            ->where('event_id', $eventId)
            ->where('total_points', '>=', $this->eventPrize->min_points_required)
            ->where('status', LotteryTicket::STATUS_ACTIVE)
            ->whereHas('participant.account.customer', function ($q) use ($eventId, $excludeCustomerIds) {
                $q->whereDoesntHave('accounts.participants.winners', function ($wq) use ($eventId) {
                    $wq->whereHas('eventPrize', fn($eq) => $eq->where('event_prizes.event_id', $eventId));
                });

                // Also exclude staged (temporary) winners to avoid duplicates in current session
                $q->whereDoesntHave('accounts.participants.temporaryWinners', function ($twq) use ($eventId) {
                    $twq->where('event_prize_id', $eventId);
                });

                if (!empty($excludeCustomerIds)) {
                    $q->whereNotIn('customers.id', $excludeCustomerIds);
                }

                $pendingBatchCustomerIds = BulkDrawBatch::whereHas('eventPrize', fn($ep) => $ep->where('event_id', $eventId))
                    ->whereIn('status', ['PENDING', 'PROCESSING', 'COMPLETED'])
                    ->get()
                    ->pluck('results')
                    ->flatten(1)
                    ->pluck('customer.id')
                    ->unique()
                    ->filter()
                    ->toArray();

                if (!empty($pendingBatchCustomerIds)) {
                    $q->whereNotIn('customers.id', $pendingBatchCustomerIds);
                }
            });

        if ($region) {
            $query->whereHas('participant.account.branch', function ($q) use ($region) {
                $q->where('region', $region);
            });
        }

        $tickets = $query->get();
        if ($tickets->isEmpty())
            return null;

        $totalPoints = $tickets->sum('total_points');
        $winningOffset = mt_rand(1, $totalPoints);

        $currentOffset = 0;
        foreach ($tickets as $ticket) {
            $currentOffset += $ticket->total_points;
            if ($winningOffset <= $currentOffset) {
                return $ticket;
            }
        }

        return $tickets->first();
    }

    public function exportCsv($extension = 'csv')
    {
        $winnersToExport = [];
        if (empty($winnersToExport) || count($winnersToExport) == 0) {
            $winnersToExport = Winner::where('event_prize_id', $this->eventPrize->id)
                ->where('draw_session_id', $this->drawSessionId)
                ->get()
                ->map(fn(Winner $w) => $w->getDataBulk());
        }

        if (empty($winnersToExport))
            return null;

        $filename = "grand_winners_" . str_replace([' ', '/', '\\'], '_', $this->eventPrize->prize->prize_name) . "_" . now()->format('Ymd_His') . "." . $extension;

        if ($extension === 'xls') {
            return response()->streamDownload(function () use ($winnersToExport) {
                echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
                <head><meta http-equiv="content-type" content="text/html; charset=utf-8"/></head>
                <body><table border="1">
                    <thead>
                        <tr style="background-color: #f3f4f6;">
                            <th>CIF</th>
                            <th>Account Number</th>
                            <th>Name</th>
                            <th>Wilayah</th>
                            <th>Branch</th>
                            <th>Prize Name</th>
                            <th>Lucky Number</th>
                            <th>Points</th>
                            <th>Product Name</th>
                            <th>Product Code</th>
                            <th>Account Status</th>
                            <th>Drawn At</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($winnersToExport as $w) {
                    echo '<tr>
                        <td>' . ($w['cif'] ?? ($w['cif'] ?? 'N/A')) . '</td>
                        <td>' . ($w['account']['account_number'] ?? $w['account_number']) . '</td>
                        <td>' . ($w['participant']['name'] ?? ($w['name'] ?? 'N/A')) . '</td>
                        <td>' . ($w['region'] ?? ($w['region'] ?? 'N/A')) . '</td>
                        <td>' . ($w['branch_name'] ?? 'N/A') . '</td>
                        <td>' . ($w['prize_name'] ?? 'N/A') . '</td>
                        <td>' . $w['lucky_number'] . '</td>
                        <td>' . ($w['ticket']['total_points'] ?? 0) . '</td>
                        <td>' . ($w['product_name'] ?? 'N/A') . '</td>
                        <td>' . ($w['product_code'] ?? 'N/A') . '</td>
                        <td>' . ($w['account_status'] ?? 'N/A') . '</td>
                        <td>' . now()->format('Y-m-d H:i:s') . '</td>
                    </tr>';
                }
                echo '</tbody></table></body></html>';
            }, $filename, ['Content-Type' => 'application/vnd.ms-excel']);
        }

        return response()->streamDownload(function () use ($winnersToExport) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['CIF', 'Account Number', 'Name', 'Wilayah', 'Branch', 'Prize Name', 'Lucky Number', 'Points', 'Product Name', 'Product Code', 'Account Status', 'Drawn At']);
            foreach ($winnersToExport as $w) {
                fputcsv($file, [
                    $w['cif'] ?? ($w['cif'] ?? 'N/A'),
                    $w['account']['account_number'] ?? $w['account_number'],
                    $w['participant']['name'] ?? ($w['name'] ?? 'N/A'),
                    $w['region'] ?? ($w['region'] ?? 'N/A'),
                    $w['branch_name'] ?? $w['branch_name'],
                    $w['prize_name'] ?? 'N/A',
                    $w['lucky_number'],
                    $w['ticket']['total_points'] ?? 0,
                    $w['product_name'] ?? 'N/A',
                    $w['product_code'] ?? 'N/A',
                    $w['account_status'] ?? 'N/A',
                    now()->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        }, $filename);
    }

    public function exportExcel()
    {
        return $this->exportCsv('xls');
    }

    public function render()
    {
        return view('livewire.public.grand-drawing')
            ->layout('layouts.guest');
    }
}
