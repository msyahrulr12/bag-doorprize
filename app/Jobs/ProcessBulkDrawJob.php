<?php

namespace App\Jobs;

use App\Models\BulkDrawBatch;
use App\Models\EventPrize;
use App\Models\LotteryTicket;
use App\Models\Setting;
use App\Models\TemporaryWinner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBulkDrawJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 600; // 10 minutes
    public $batchId;

    public function __construct($batchId)
    {
        $this->batchId = $batchId;
        $this->onQueue('draws');
    }

    public function handle(): void
    {
        $batch = BulkDrawBatch::findOrFail($this->batchId);
        $batch->update(['status' => 'PROCESSING']);

        try {
            $eventPrize = EventPrize::with(['event', 'prize'])->findOrFail($batch->event_prize_id);
            $eventId = $eventPrize->event_id;
            $drawCount = $batch->total_winners;

            // 1. Fetch Region Weights
            $weightsSetting = Setting::where('key', 'region_weights')->first();
            $weights = $weightsSetting ? json_decode($weightsSetting->value, true) : [
                'Jawa' => 50,
                'Sumatera' => 20,
                'Sulawesi' => 20,
                'Lainnya' => 10,
            ];

            // 2. Fetch ALL eligible tickets ONCE to save memory/time
            Log::info("BulkDraw - Fetching eligible tickets for event: {$eventId}");
            $tickets = \DB::table('lottery_tickets')
                ->join('participants', 'participants.id', '=', 'lottery_tickets.participant_id')
                ->join('accounts', 'accounts.id', '=', 'participants.account_id')
                ->join('branches', 'branches.id', '=', 'accounts.branch_id')
                ->join('customers', 'customers.id', '=', 'accounts.customer_id')
                ->select([
                    'lottery_tickets.id',
                    'lottery_tickets.total_points',
                    'lottery_tickets.range_start',
                    'lottery_tickets.range_end',
                    'lottery_tickets.participant_id',
                    'accounts.customer_id as customer_id',
                    'customers.cif',
                    'branches.id as branch_id',
                    'branches.region',
                    'branches.branch_code',
                    'branches.branch_name',
                    'branches.company_book as branch_company_book',
                    'participants.participant_name',
                    'participants.participant_email',
                    'participants.participant_phone_number',
                    'accounts.account_number',
                    'accounts.status as account_status',
                ])
                ->where('lottery_tickets.event_id', $eventId)
                ->where('lottery_tickets.status', LotteryTicket::STATUS_ACTIVE)
                ->where('lottery_tickets.total_points', '>=', $eventPrize->min_points_required)
                ->whereNull('lottery_tickets.deleted_at')
                ->whereNull('participants.deleted_at')
                ->whereNotExists(function ($query) use ($eventId) {
                    $query->select(\DB::raw(1))
                        ->from('winners')
                        ->join('event_prizes', 'event_prizes.id', '=', 'winners.event_prize_id')
                        ->join('participants as p_check', 'p_check.id', '=', 'winners.participant_id')
                        ->join('accounts as a_check', 'a_check.id', '=', 'p_check.account_id')
                        ->whereColumn('a_check.customer_id', 'customers.id')
                        ->where('event_prizes.event_id', $eventId)
                        ->whereNull('winners.deleted_at');
                })
                ->whereNotExists(function ($query) use ($eventId) {
                    $query->select(\DB::raw(1))
                        ->from('temporary_winners')
                        ->join('event_prizes as ep_check', 'ep_check.id', '=', 'temporary_winners.event_prize_id')
                        ->whereColumn('temporary_winners.participant_cif', 'customers.cif')
                        ->where('ep_check.event_id', $eventId)
                        ->whereNull('temporary_winners.deleted_at');
                })
                ->get();

            Log::info("BulkDraw - Found " . $tickets->count() . " eligible tickets.");

            if ($tickets->isEmpty()) {
                throw new \Exception("No eligible winners found.");
            }

            $results = [];
            $usedCustomerIds = [];

            // Group tickets by region
            $regionGroups = $tickets->groupBy('region');

            for ($i = 0; $i < $drawCount; $i++) {
                // Determine target region
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

                $selectedTicket = $this->pickFromCollection($regionGroups, $targetRegion, $usedCustomerIds, $weights);
                if (!$selectedTicket) $selectedTicket = $this->pickFromCollection($regionGroups, null, $usedCustomerIds, $weights);

                if (!$selectedTicket) break;

                $usedCustomerIds[] = $selectedTicket->customer_id;
                $luckyNumber = $this->generateLuckyNumber($selectedTicket);

                $wData = [
                    'participant_id' => $selectedTicket->participant_id,
                    'participant_cif' => $selectedTicket->cif,
                    'participant_account_number' => $selectedTicket->account_number,
                    'participant_name' => $selectedTicket->participant_name,
                    'participant_email' => $selectedTicket->participant_email,
                    'participant_phone_number' => $selectedTicket->participant_phone_number ?? 'N/A',
                    'event_prize_id' => $batch->event_prize_id,
                    'draw_session_id' => $batch->draw_session_id,
                    'winning_number' => $luckyNumber,
                    'drawn_at' => now(),
                    'drawn_by' => $batch->created_by ?? 'System Batch',
                    'lottery_ticket_id' => $selectedTicket->id,
                    'total_points' => $selectedTicket->total_points,
                    'range_start' => $selectedTicket->range_start,
                    'range_end' => $selectedTicket->range_end,
                    'status' => 'PENDING',
                    'branch_id' => $selectedTicket->branch_id,
                    'branch_code' => $selectedTicket->branch_code,
                    'branch_name' => $selectedTicket->branch_name,
                    'branch_company_book' => $selectedTicket->branch_company_book,
                    'branch_region' => $selectedTicket->region,
                    'account_status' => $selectedTicket->account_status,
                ];

                // Save to temporary winners table
                $tw = TemporaryWinner::create($wData);

                // For the batch results JSON compat
                $results[] = $tw->getData();

                $batch->update([
                    'processed_winners' => $i + 1,
                    'results' => $results
                ]);
            }

            $finalStatus = $batch->status === 'CANCELLED' ? 'CANCELLED' : 'COMPLETED';
            $batch->update([
                'status' => $finalStatus,
                'processed_winners' => count($results),
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error("BulkDraw Error: " . $e->getMessage());
            $batch->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function pickFromCollection($regionGroups, $targetRegion, $usedCustomerIds, $weights)
    {
        $eligibleTickets = collect();
        if ($targetRegion) {
            $eligibleTickets = ($regionGroups[$targetRegion] ?? collect())->reject(fn($t) => in_array($t->customer_id, $usedCustomerIds));
            if ($eligibleTickets->isEmpty()) {
                $otherRegions = array_keys(collect($weights)->sortByDesc(fn($w) => $w)->toArray());
                foreach ($otherRegions as $region) {
                    if ($region === $targetRegion) continue;
                    $eligibleTickets = ($regionGroups[$region] ?? collect())->reject(fn($t) => in_array($t->customer_id, $usedCustomerIds));
                    if ($eligibleTickets->isNotEmpty()) break;
                }
            }
        } else {
            foreach ($regionGroups as $group) {
                $eligibleTickets = $eligibleTickets->concat($group->reject(fn($t) => in_array($t->customer_id, $usedCustomerIds)));
            }
        }

        if ($eligibleTickets->isEmpty()) return null;
        $totalPoints = $eligibleTickets->sum('total_points');
        $winningOffset = mt_rand(1, $totalPoints);
        $currentOffset = 0;
        foreach ($eligibleTickets as $ticket) {
            $currentOffset += $ticket->total_points;
            if ($winningOffset <= $currentOffset) return $ticket;
        }
        return $eligibleTickets->first();
    }

    private function generateLuckyNumber($ticket): string
    {
        $start = $ticket->range_start;
        $end = $ticket->range_end;
        if ($start === $end) return $start;
        if (preg_match('/^([A-Z]*)(\d+)$/', $start, $startMatch) && preg_match('/^([A-Z]*)(\d+)$/', $end, $endMatch)) {
            $prefix = $startMatch[1];
            $startNum = (int) $startMatch[2];
            $endNum = (int) $endMatch[2];
            $randomNum = mt_rand($startNum, $endNum);
            return $prefix . str_pad($randomNum, strlen($startMatch[2]), '0', STR_PAD_LEFT);
        }
        return $start;
    }
}
