<?php

namespace App\Livewire\Public;

use App\Models\EventPrize;
use App\Models\LotteryTicket;
use App\Models\Setting;
use App\Models\Winner;
use App\Models\TemporaryWinner;
use App\Models\Prize;
use App\Models\BulkDrawBatch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

    public int $totalDataToProcess = 0;

    public $winner = null;
    public $pendingWinners = [];
    public $candidates = [];
    public array $randomData = [
        'names' => [],
        'branches' => [],
        'lucky_numbers' => [],
    ];

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
        return Winner::where('event_prize_id', $this->eventPrize->id)
            ->orderBy('id', 'desc')
            ->with(['participant.account.branch', 'participant.account.customer'])
            ->paginate(30);
    }

    public function mount($uuid)
    {
        $this->uuid = $uuid;
        $this->eventPrize = EventPrize::with(['event', 'prize'])->where('uuid', $uuid)->firstOrFail();
        $this->drawSessionId = $this->eventPrize->draw_session_id;
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
        // On page change, only refresh the confirmed winners display
        if (!$this->isPreview) {
            $this->refreshConfirmedWinnersDisplay();
        }
    }

    /**
     * Refresh only the confirmed (paginated) winners for table display.
     * This is called on page change and does NOT touch preview/temporary state.
     */
    private function refreshConfirmedWinnersDisplay()
    {
        $paginatedWinners = $this->paginatedWinners();
        if ($paginatedWinners->count() > 0) {
            $firstWinner = $paginatedWinners->first();
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
            $this->winners = collect($paginatedWinners->items())->map(fn(Winner $w) => $w->getDataBulk())->split(3)->toArray();
        } else {
            $this->winners = [];
            $this->winner = null;
        }
    }

    private function checkWinner()
    {
        if ($this->isDrawing) {
            return false;
        }
        $this->isPreview = false;

        $cacheKey = 't_winners_' . $this->eventPrize->id;

        // Step 1: Check for temporary (preview) winners
        if ($this->drawSessionId) {
            $hasCachedPreview = Cache::has($cacheKey);

            if ($hasCachedPreview) {
                // Use cached preview data
                $this->winners = json_decode(Cache::get($cacheKey), true);
                $this->isPreview = true;
                $this->totalWinners = collect($this->winners)->flatten(1)->count();
                // Rebuild pendingWinners from the cached split data
                $this->pendingWinners = collect($this->winners)->flatten(1)->values()->toArray();
                return true;
            }

            // No cache — query temporary winners from DB
            $tempWinners = TemporaryWinner::where('draw_session_id', $this->drawSessionId)
                ->where('event_prize_id', $this->eventPrize->id)
                ->with(['participant.account.branch', 'participant.account.product', 'eventPrize.prize'])
                ->get();

            if ($tempWinners->count() > 0) {
                $this->pendingWinners = $tempWinners->map(fn($tw) => $tw->getData())->toArray();
                $this->isPreview = true;
                $this->totalWinners = count($this->pendingWinners);

                // For batch view, show new winners split into 3 columns
                $displayWinners = collect($this->pendingWinners);
                $this->winners = $displayWinners->split(3)->toArray();

                // Cache the preview data
                if (count($this->winners) > 0) {
                    Cache::put($cacheKey, json_encode($this->winners), 3600);
                }
                return true;
            }
        }

        // Step 2: No temporary winners — show confirmed (paginated) winners
        $this->refreshConfirmedWinnersDisplay();
        return !empty($this->winners);
    }

    public function startDrawing()
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
        // TemporaryWinner::where('draw_session_id', $this->drawSessionId)
        //     ->where('event_prize_id', $this->eventPrize->id)
        //     ->delete();

        $totalToDraw = $this->eventPrize->split_draw > 0 && $this->eventPrize->split_draw <= $remainingQuantity ? $this->eventPrize->split_draw : $remainingQuantity;

        // Create a batch record
        $batch = BulkDrawBatch::create([
            'event_prize_id' => $this->eventPrize->id,
            'draw_session_id' => $this->drawSessionId,
            'total_winners' => $totalToDraw,
            'status' => 'PENDING',
            'created_by' => Auth::user()->name ?? 'Public Guest',
        ]);

        \OwenIt\Auditing\Models\Audit::create([
            'user_type'      => Auth::check() ? get_class(Auth::user()) : null,
            'user_id'        => Auth::id(),
            'event'          => 'started_bulk_draw',
            'auditable_type' => get_class($this->eventPrize),
            'auditable_id'   => $this->eventPrize->id,
            'old_values'     => [],
            'new_values'     => ['batch_id' => $batch->id, 'total_to_draw' => $totalToDraw, 'session_id' => $this->drawSessionId],
            'url'            => request()->fullUrl(),
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
            'tags'           => 'doorprize,bulk_draw,start',
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

        // Clear preview cache when starting a new draw
        Cache::forget('t_winners_' . $this->eventPrize->id);

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
            if ($batch->status === 'COMPLETED' && empty($this->candidates)) {
                $candidateTickets = LotteryTicket::query()
                    ->where('event_id', $this->eventPrize->event_id)
                    ->where('status', LotteryTicket::STATUS_ACTIVE)
                    ->whereIn('participant_id', function ($q) {
                        $q->select('participant_id')
                            ->from('lottery_tickets')
                            ->where('event_id', $this->eventPrize->event_id)
                            ->where('status', LotteryTicket::STATUS_ACTIVE)
                            ->whereNull('deleted_at')
                            ->groupBy('participant_id')
                            ->havingRaw('SUM(total_points) >= ?', [$this->eventPrize->min_points_required])
                            ->when($this->eventPrize->max_points_required, function ($q) {
                                $q->havingRaw('SUM(total_points) <= ?', [$this->eventPrize->max_points_required]);
                            });
                    })
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

        \OwenIt\Auditing\Models\Audit::create([
            'user_type'      => Auth::check() ? get_class(Auth::user()) : null,
            'user_id'        => Auth::id(),
            'event'          => 'stopped_bulk_draw',
            'auditable_type' => get_class($this->eventPrize),
            'auditable_id'   => $this->eventPrize->id,
            'old_values'     => [],
            'new_values'     => ['batch_id' => $this->batchId, 'session_id' => $this->drawSessionId],
            'url'            => request()->fullUrl(),
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
            'tags'           => 'doorprize,bulk_draw,stop',
        ]);

        $this->isStopping = true;
        $this->finalizeResults();
    }

    private function finalizeResults()
    {
        // $batch = BulkDrawBatch::find($this->batchId);

        $this->batchId = null;
        $this->isStopping = false;
        $this->isDrawing = false;
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
        $this->batchId = null;
        $this->isReadyToReveal = false;

        // Lightweight refresh — only update remaining count, skip random participant data
        // (random data is only needed for the shuffle animation which is already over)
        $this->eventPrize->refresh();
        $this->totalDataToProcess = $this->eventPrize?->remaining_quantity && $this->eventPrize?->remaining_quantity < $this->eventPrize?->split_draw
            ? $this->eventPrize?->remaining_quantity
            : $this->eventPrize?->split_draw;

        $this->checkWinner();

        if (empty($this->pendingWinners)) {
            $this->dispatch('error', message: 'Winner data not found. Animation stopped.');
            return;
        }

        $this->dispatch('success', message: 'Winners have been picked and prepared for review.');
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
                // $tw->delete();
            }
            DB::commit();

            // Clear the preview cache so stale temporary data doesn't persist
            $cacheKey = 't_winners_' . $this->eventPrize->id;
            Cache::forget($cacheKey);

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

            // Delete cache if cache exists
            $cacheKey = 't_winners_' . $this->eventPrize->id;
            Cache::forget($cacheKey);

            // Delete Temporary Winners
            TemporaryWinner::where('draw_session_id', $this->drawSessionId)
                ->where('event_prize_id', $this->eventPrize->id)
                ->delete();

            // Delete Confirmed Winners for this session and prize
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

    public function clearWinner()
    {
        $this->pendingWinners = [];
        $this->winners = [];
        $this->isPreview = false;
    }

    public function exportCsv($extension = 'csv')
    {
        // Fetch confirmed winners
        $confirmedWinners = Winner::where('event_prize_id', $this->eventPrize->id)
            ->where('draw_session_id', $this->drawSessionId)
            ->get()
            ->map(fn(Winner $w) => $w->getDataBulk());

        // Fetch temporary winners
        $tempWinners = TemporaryWinner::where('event_prize_id', $this->eventPrize->id)
            ->where('draw_session_id', $this->drawSessionId)
            ->get()
            ->map(fn(TemporaryWinner $tw) => $tw->getData());

        $winnersToExport = $confirmedWinners->concat($tempWinners)->toArray();

        if (empty($winnersToExport)) {
            $this->dispatch('error', message: 'No winners found to export.');
            return;
        }

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
                        <td>' . ($w['drawn_at'] ?? now()->format('Y-m-d H:i:s')) . '</td>
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
                    $w['drawn_at'] ?? now()->format('Y-m-d H:i:s'),
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
        return view('livewire.public.bulk-drawing-new')
            ->layout('layouts.guest');
    }
}
