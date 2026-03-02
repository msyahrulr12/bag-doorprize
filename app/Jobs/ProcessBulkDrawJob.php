<?php

namespace App\Jobs;

use App\Models\BulkDrawBatch;
use App\Models\EventPrize;
use App\Models\LotteryTicket;
use App\Models\Setting;
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
                // ->join('event_prizes', 'event_prizes.event_id', '=', 'lottery_tickets.event_id')
                // ->join('prizes', 'prizes.id', '=', 'event_prizes.prize_id')
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
                    // 'prizes.prize_name',
                    // 'prizes.id as prize_id',
                    // 'prizes.tier as prize_tier',
                    // 'prizes.tier as prize_tier'
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
                ->get();

            Log::info("BulkDraw - Found " . $tickets->count() . " eligible tickets.");

            if ($tickets->isEmpty()) {
                throw new \Exception("No eligible winners found.");
            }

            $winners = [];
            $usedCustomerIds = [];

            // 1.5 Fetch customers who are already in other active/completed batches for this event
            // to avoid duplicates if multiple batches are drawn or pending confirmation
            $otherBatches = \App\Models\BulkDrawBatch::whereHas('eventPrize', function ($q) use ($eventId) {
                $q->where('event_id', $eventId);
            })
                ->whereIn('status', ['PENDING', 'PROCESSING', 'COMPLETED'])
                ->where('id', '!=', $this->batchId)
                ->get();

            foreach ($otherBatches as $ob) {
                if ($ob->results) {
                    foreach ($ob->results as $result) {
                        if (isset($result['customer']['id'])) {
                            $usedCustomerIds[] = $result['customer']['id'];
                        }
                    }
                }
            }

            $usedCustomerIds = array_unique($usedCustomerIds);

            // Group tickets by region for faster selection
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

                if (!$selectedTicket) {
                    // Fallback to any region if target and others failed
                    $selectedTicket = $this->pickFromCollection($regionGroups, null, $usedCustomerIds, $weights);
                }

                if (!$selectedTicket) {
                    Log::warning("BulkDraw - Could only find " . count($winners) . " winners out of " . $drawCount);
                    break;
                }

                $usedCustomerIds[] = $selectedTicket->customer_id;

                $winners[] = [
                    'cif' => $selectedTicket->cif,
                    'name' => $selectedTicket->participant_name,
                    'account' => [
                        'account_number' => $selectedTicket->account_number,
                        'branch' => [
                            'id' => $selectedTicket->branch_id,
                            'code' => $selectedTicket->branch_code,
                            'region' => $selectedTicket->region,
                            'branch_name' => $selectedTicket->branch_name,
                            'company_book' => $selectedTicket->branch_company_book,
                        ],
                    ],
                    'ticket' => [
                        'id' => $selectedTicket->id,
                        'total_points' => $selectedTicket->total_points,
                        'range_start' => $selectedTicket->range_start,
                        'range_end' => $selectedTicket->range_end,
                    ],
                    'participant' => [
                        'id' => $selectedTicket->participant_id,
                        'participant_name' => $selectedTicket->participant_name,
                        'participant_cif' => $selectedTicket->cif, // Actually customer cif
                        'participant_email' => $selectedTicket->participant_email,
                        'participant_phone_number' => $selectedTicket->participant_phone_number,
                    ],
                    'customer' => [
                        'id' => $selectedTicket->customer_id,
                    ],
                    'lucky_number' => $this->generateLuckyNumber($selectedTicket),
                    'winning_number' => $selectedTicket->range_start === $selectedTicket->range_end
                        ? $selectedTicket->range_start
                        : "{$selectedTicket->range_start} - {$selectedTicket->range_end}",
                    'region' => $selectedTicket->region,
                    'branch_name' => $selectedTicket->branch_name
                ];

                if ($i % 5 == 0) {
                    $batch->refresh();
                    if ($batch->status === 'CANCELLED') {
                        Log::info("BulkDraw - Job stop signaled for batch: {$this->batchId} - but continuing to 100% as requested.");
                    }
                    $batch->update([
                        'processed_winners' => $i + 1,
                        'results' => $winners
                    ]);
                }
            }

            $finalStatus = $batch->status === 'CANCELLED' ? 'CANCELLED' : 'COMPLETED';
            $batch->update([
                'status' => $finalStatus,
                'processed_winners' => $drawCount,
                'results' => $winners,
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
            // Try target region first
            $eligibleTickets = ($regionGroups[$targetRegion] ?? collect())
                ->reject(fn($t) => in_array($t->customer_id, $usedCustomerIds));

            // If empty, try other regions in order of weight
            if ($eligibleTickets->isEmpty()) {
                $otherRegions = array_keys(collect($weights)->sortByDesc(fn($w) => $w)->toArray());
                foreach ($otherRegions as $region) {
                    if ($region === $targetRegion)
                        continue;
                    $eligibleTickets = ($regionGroups[$region] ?? collect())
                        ->reject(fn($t) => in_array($t->customer_id, $usedCustomerIds));
                    if ($eligibleTickets->isNotEmpty())
                        break;
                }
            }
        } else {
            // Pick from all remaining tickets
            foreach ($regionGroups as $region => $group) {
                $eligibleTickets = $eligibleTickets->concat(
                    $group->reject(fn($t) => in_array($t->customer_id, $usedCustomerIds))
                );
            }
        }

        if ($eligibleTickets->isEmpty()) {
            return null;
        }

        // Weighted random selection from the collection
        $totalPoints = $eligibleTickets->sum('total_points');
        $winningOffset = mt_rand(1, $totalPoints);

        $currentOffset = 0;
        foreach ($eligibleTickets as $ticket) {
            $currentOffset += $ticket->total_points;
            if ($winningOffset <= $currentOffset) {
                return $ticket;
            }
        }

        return $eligibleTickets->first();
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
}
