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

        // Always check if there are temporary winners first (for current session)
        $tempWinners = TemporaryWinner::where('draw_session_id', $this->drawSessionId)
            ->where('event_prize_id', $this->eventPrize->id)
            ->get();

        if ($tempWinners->count() > 0) {
            $this->pendingWinners = $tempWinners->map(fn($tw) => $tw->getData())->toArray();
            $this->winner = end($this->pendingWinners);
            $this->isPreview = true;

            // For the preview table display
            $this->winners = collect($this->pendingWinners)->split(min(3, count($this->pendingWinners)))->toArray();
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
                $this->winners = collect($paginatedWinners->items())->map(fn(Winner $w) => $w->getDataBulk())->split(2)->toArray();
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
        if ($this->eventPrize->remaining_quantity <= 0) {
            $this->dispatch('error', message: 'Prize exhausted.');
            return;
        }

        if (!$this->drawSessionId) {
            $this->dispatch('error', message: 'No active draw session selected.');
            return;
        }

        $this->winner = null;
        $this->isPreview = false;
        $this->isDrawing = true;
        $this->dispatch('trigger-animation');
    }

    public function performDraw()
    {
        $splitDraw = (int) ($this->eventPrize->split_draw ?? 1);
        if ($splitDraw <= 0)
            $splitDraw = 1;

        $batchWinners = [];
        $eventId = $this->eventPrize->event_id;

        $weightsSetting = Setting::where('key', 'region_weights')->first();
        $weights = $weightsSetting ? json_decode($weightsSetting->value, true) : [
            'Jawa' => 50,
            'Sumatera' => 20,
            'Sulawesi' => 20,
            'Lainnya' => 10,
        ];

        for ($i = 0; $i < $splitDraw; $i++) {
            $this->eventPrize->refresh();
            if ($this->eventPrize->remaining_quantity <= count($batchWinners))
                break;

            $rand = mt_rand(1, 100);
            $targetRegion = 'Lainnya';
            $cumulative = 0;
            foreach ($weights as $region => $weight) {
                $cumulative += $weight;
                if ($rand <= $cumulative) {
                    $targetRegion = $region;
                    break;
                }
            }

            $winnerTicket = $this->findWinnerInRegion($targetRegion, $eventId, array_column($batchWinners, 'customer_id'));

            if (!$winnerTicket) {
                $otherRegions = array_keys(collect($weights)->sortByDesc(fn($w) => $w)->toArray());
                foreach ($otherRegions as $region) {
                    if ($region === $targetRegion)
                        continue;
                    $winnerTicket = $this->findWinnerInRegion($region, $eventId, array_column($batchWinners, 'customer_id'));
                    if ($winnerTicket)
                        break;
                }
            }

            if (!$winnerTicket) {
                $winnerTicket = $this->findWinnerInRegion(null, $eventId, array_column($batchWinners, 'customer_id'));
            }

            if (!$winnerTicket) {
                if (empty($batchWinners)) {
                    $this->isDrawing = false;
                    $this->dispatch('error', message: 'No eligible winner found.');
                    return;
                }
                break;
            }

            $winnerTicket->load(['participant.account.branch', 'participant.account.customer']);
            $luckyNumber = $this->generateLuckyNumber($winnerTicket);

            $batchWinners[] = [
                'ticket' => $winnerTicket->toArray(),
                'participant' => $winnerTicket->participant->toArray(),
                'customer' => $winnerTicket->participant->account->customer->toArray(),
                'customer_id' => $winnerTicket->participant->account->customer->id,
                'lottery_ticket_id' => $winnerTicket->id,
                'participant_id' => $winnerTicket->participant_id,
                'lucky_number' => $luckyNumber,
                'winning_number' => $winnerTicket->range_start === $winnerTicket->range_end
                    ? $winnerTicket->range_start
                    : "{$winnerTicket->range_start} - {$winnerTicket->range_end}",
                'draw_session_id' => $this->drawSessionId,
                'name' => $winnerTicket->participant->participant_name,
                'branch_name' => $winnerTicket->participant->account->branch->branch_name,
                'cif' => $winnerTicket->participant->participant_cif,
                'region' => $winnerTicket->participant->account->branch->region,
            ];
        }

        $candidateTickets = LotteryTicket::query()
            ->where('event_id', $eventId)
            ->where('total_points', '>=', $this->eventPrize->min_points_required)
            ->where('status', LotteryTicket::STATUS_ACTIVE)
            ->inRandomOrder()
            ->limit(20)
            ->get();

        $this->candidates = $candidateTickets->map(fn($t) => $this->generateLuckyNumber($t))->toArray();

        $this->pendingWinners = $batchWinners;
        $this->pendingWinner = $batchWinners[0] ?? null;
        $this->isPreview = true;
        $this->winner = null;
    }

    public function finishDrawing()
    {
        if (empty($this->pendingWinners)) {
            $this->isDrawing = false;
            $this->dispatch('error', message: 'Winner data not found. Animation stopped.');
            return;
        }

        $winnersToSave = [];
        foreach ($this->pendingWinners as $w) {
            $participant = Participant::with(['account.branch', 'account.customer'])->findOrFail($w['participant']['id'] ?? $w['participant_id']);
            $ticket = LotteryTicket::findOrFail($w['ticket']['id'] ?? $w['lottery_ticket_id']);

            $winnersToSave[] = [
                'participant_id' => $participant->id,
                'participant_cif' => $participant->participant_cif,
                'participant_account_number' => $participant->participant_account_number,
                'participant_name' => $participant->participant_name,
                'participant_email' => $participant->participant_email,
                'participant_phone_number' => $participant->participant_phone_number,
                'event_prize_id' => $this->eventPrize->id,
                'draw_session_id' => $this->drawSessionId,
                'winning_number' => $w['lucky_number'],
                'drawn_at' => now(),
                'drawn_by' => Auth::user()->name ?? 'Guest User',
                'lottery_ticket_id' => $ticket->id,
                'total_points' => $ticket->total_points ?? 0,
                'range_start' => $ticket->range_start ?? 'N/A',
                'range_end' => $ticket->range_end ?? 'N/A',
                'status' => Winner::STATUS_PENDING,
                'branch_id' => $participant->account->branch_id,
                'branch_code' => $participant->account->branch->branch_code,
                'branch_name' => $participant->account->branch->branch_name,
                'branch_company_book' => $participant->account->branch->company_book,
                'branch_region' => $participant->account->branch->region,
                'account_status' => $participant->account->status,
            ];
        }

        $this->isDrawing = false;
        $this->isPreview = true;

        if ($this->enableRedraw) {
            foreach ($winnersToSave as $wData) {
                TemporaryWinner::create($wData);
            }
            $this->checkWinner();
            $this->dispatch('success', message: 'Winners have been picked and staged for review.');
        } else {
            foreach ($winnersToSave as $wData) {
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
            }
            $this->dispatch('success', message: 'Winner confirmed and saved successfully!');
            $this->dispatch('winner-confirmed');
            $this->winner = null;
            $this->isPreview = false;
            $this->checkWinner();
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
                $tw->delete();
            }
            DB::commit();

            $this->dispatch('success', message: 'All winners have been confirmed!');
            $this->dispatch('winner-confirmed');
            $this->winner = null;
            $this->isPreview = false;
            $this->pendingWinners = [];
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

        $this->winner = null;
        $this->isPreview = false;
        $this->pendingWinners = [];
        $this->checkWinner();

        $this->dispatch('success', message: 'Winners have been reset. You can draw again.');
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
        if (!empty($this->pendingWinners)) {
            $winnersToExport = $this->pendingWinners;
        } elseif ($this->winner) {
            $winnersToExport[] = $this->winner;
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
                            <th>Prize</th>
                            <th>Lucky Number</th>
                            <th>Points</th>
                            <th>Branch</th>
                            <th>Drawn At</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($winnersToExport as $w) {
                    echo '<tr>
                        <td>' . ($w['participant']['participant_cif'] ?? ($w['cif'] ?? 'N/A')) . '</td>
                        <td>' . ($w['participant']['participant_account_number'] ?? 'N/A') . '</td>
                        <td>' . ($w['participant']['participant_name'] ?? ($w['name'] ?? 'N/A')) . '</td>
                        <td>' . $this->eventPrize->prize->prize_name . '</td>
                        <td>' . $w['lucky_number'] . '</td>
                        <td>' . ($w['ticket']['total_points'] ?? 0) . '</td>
                        <td>' . ($w['branch_name'] ?? 'N/A') . '</td>
                        <td>' . now()->format('Y-m-d H:i:s') . '</td>
                    </tr>';
                }
                echo '</tbody></table></body></html>';
            }, $filename, ['Content-Type' => 'application/vnd.ms-excel']);
        }

        return response()->streamDownload(function () use ($winnersToExport) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['CIF', 'Account Number', 'Name', 'Prize', 'Lucky Number', 'Points', 'Branch', 'Drawn At']);
            foreach ($winnersToExport as $w) {
                fputcsv($file, [
                    $w['participant']['participant_cif'] ?? ($w['cif'] ?? 'N/A'),
                    $w['participant']['participant_account_number'] ?? 'N/A',
                    $w['participant']['participant_name'] ?? ($w['name'] ?? 'N/A'),
                    $this->eventPrize->prize->prize_name,
                    $w['lucky_number'],
                    $w['ticket']['total_points'] ?? 0,
                    $w['branch_name'] ?? 'N/A',
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
