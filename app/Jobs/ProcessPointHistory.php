<?php

namespace App\Jobs;

use App\Events\PointHistoryProcessed;
use App\Models\Account;
use App\Models\Customer;
use App\Models\LotteryTicket;
use App\Models\Participant;
use App\Models\PointHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessPointHistory implements ShouldQueue
{
    use Queueable;

    public $timeout = 600; // Increased timeout for batch safety

    /**
     * Create a new job instance.
     */
    public function __construct(
        private array $customers,
        private array $products,
        private array $branches,
        private int $month,
        private int $year,
        private string $type, // 'ntb' or 'etb'
        private array $settings,
        private int $eventId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->customers)) {
            return;
        }

        $customerBatch = array_map(fn($c) => (array) $c, $this->customers);
        $chunkSize = count($customerBatch);

        Log::info(sprintf('Processing %s Batch for %d records (%d/%d)', $this->type, $chunkSize, $this->month, $this->year));

        try {
            // 1. Bulk Upsert Customers
            $this->processCustomersBulk($customerBatch);

            // 2. Bulk Upsert Accounts
            $this->processAccountsBulk($customerBatch);

            // 3. Bulk Upsert Point Histories & get participant map
            $pointHistories = $this->processPointHistoriesBulk($customerBatch);

            if ($this->eventId) {
                // 4. Bulk Upsert Participants & Get IDs
                $participantMap = $this->processParticipantsBulk($customerBatch);

                // 5. Dispatch for Lottery Tickets (Async via Listener)
                event(new PointHistoryProcessed($pointHistories, $participantMap, $this->eventId, $this->month, $this->year));
            }
        } catch (\Exception $e) {
            Log::error("Batch processing failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    private function processCustomersBulk(array $customers): void
    {
        $upserts = [];
        $now = now();
        foreach ($customers as $customer) {
            $branchId = $this->branches[trim($customer['cus_open_branch'] ?? '')] ?? null;
            if (!$branchId)
                continue;

            // Use CIF as key to prevent duplicates in the same batch
            $upserts[$customer['cif']] = [
                'branch_id'     => $branchId,
                'name'          => $customer['name'],
                'cif'           => $customer['cif'],
                'email'         => $customer['email'] ?? null,
                'status'        => Customer::STATUS_ACTIVE,
                'date_of_birth' => (isset($customer['date_of_birth']) && $customer['date_of_birth'] !== '') ? $customer['date_of_birth'] : null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        if (!empty($upserts)) {
            Customer::upsert(array_values($upserts), ['cif'], ['name', 'email', 'branch_id', 'updated_at']);
        }
    }

    private function processAccountsBulk(array $customers): void
    {
        $cifs = array_column($customers, 'cif');
        $customerMap = Customer::whereIn('cif', $cifs)->pluck('id', 'cif')->toArray();
        $now = now();

        $upserts = [];
        foreach ($customers as $customer) {
            $customerId = $customerMap[$customer['cif']] ?? null;
            $branchId   = $this->branches[trim($customer['acc_open_branch'] ?? '')] ?? null;
            $productId  = $this->products[trim($customer['jenis_rekening'] ?? '')] ?? null;
            $accNo      = $customer['account_number'] ?? ($customer['ac_id'] ?? null);

            if (!$customerId || !$branchId || !$productId || !$accNo)
                continue;

            $inactivMarker = $customer['inactiv_marker'] ?? ($customer['inactivMarker'] ?? null);
            $excludeFlag   = $customer['exclude_flag']   ?? ($customer['excludeFlag']   ?? null);
            $confiFlag     = $customer['confi_flag']     ?? ($customer['confiFlag']     ?? null);

            if ($confiFlag !== null && $confiFlag !== '' && $confiFlag !== 'N') {
                $accountStatus = Account::STATUS_CONFI;
            } elseif ($inactivMarker !== null && $inactivMarker !== '' && $inactivMarker !== 'N') {
                $accountStatus = Account::STATUS_INACTIVE;
            } elseif ($excludeFlag !== null && $excludeFlag !== '' && $excludeFlag !== 'N') {
                $accountStatus = Account::STATUS_EXCLUDE;
            } else {
                $accountStatus = Account::STATUS_ACTIVE;
            }

            // Use account_number as key to prevent duplicates in the same batch
            $upserts[$accNo] = [
                'customer_id'            => $customerId,
                'branch_id'              => $branchId,
                'product_id'             => $productId,
                'account_number'         => $accNo,
                'account_type'           => $customer['jenis_rekening'],
                'account_opening_date'    => $customer['account_opening_date'] ?? null,
                'account_opening_balance' => $this->parseAmount($customer['account_opening_balance'] ?? 0),
                'current_balance'         => $this->parseAmount($customer['avgbal_tab'] ?? ($customer['avg_balance'] ?? 0)),
                'created_at'              => $now,
                'updated_at'              => $now,
                'status'                  => $accountStatus,
            ];
        }

        if (!empty($upserts)) {
            Account::upsert(
                array_values($upserts),
                ['account_number'],
                ['customer_id', 'branch_id', 'product_id', 'account_opening_date', 'account_opening_balance', 'current_balance', 'updated_at', 'status']
            );
        }
    }

    private function processPointHistoriesBulk(array $customers): array
    {
        // Resolve previous period (handles January → December of previous year)
        $prevMonth = ($this->month === 1) ? 12 : $this->month - 1;
        $prevYear  = ($this->month === 1) ? $this->year - 1 : $this->year;

        $baseYear  = (int) ($this->settings['base_comparison_year']  ?? 2026);
        $baseMonth = (int) ($this->settings['base_comparison_month'] ?? 1);

        $accNos     = array_filter(array_map(fn($c) => $c['account_number'] ?? ($c['ac_id'] ?? null), $customers));
        $accountMap = Account::whereIn('account_number', $accNos)->pluck('id', 'account_number')->toArray();
        $now        = now();

        // --- ETB anomaly detection map ---
        $existingMap = [];
        if ($this->type === 'etb') {
            $existing = PointHistory::whereIn('account_id', array_values($accountMap))
                ->where('year', '>=', $baseYear)
                ->where('year', '<=', $prevYear)
                ->get(['account_id', 'month', 'year']);

            foreach ($existing as $rec) {
                $existingMap[$rec->account_id][$rec->year][$rec->month] = true;
            }
        }

        // --- Previous month amounts (EARN only, ignores gap-fill phantoms) ---
        $prevAmounts = PointHistory::whereIn('account_id', array_values($accountMap))
            ->where('month', $prevMonth)
            ->where('year', $prevYear)
            ->where('description', '!=', 'BASE COMPARISON DATA (GAP FILL)')
            ->orderBy('amount', 'desc')
            ->get(['amount', 'account_id'])
            ->pluck('amount', 'account_id')
            ->toArray();

        // --- Participant & active-ticket data (needed for reset logic) ---
        $participants = Participant::whereIn('account_id', array_values($accountMap))
            ->where('event_id', $this->eventId)
            ->get(['id', 'account_id'])
            ->keyBy('account_id');

        $activeTicketPoints = [];
        if ($participants->isNotEmpty()) {
            $activeTicketPoints = LotteryTicket::whereIn('participant_id', $participants->pluck('id'))
                ->where('status', LotteryTicket::STATUS_ACTIVE)
                ->selectRaw('participant_id, SUM(total_points) as total')
                ->groupBy('participant_id')
                ->pluck('total', 'participant_id')
                ->toArray();
        }

        // --- Cumulative ledger points (before this period, re-run safe) ---
        $currentLedgerPoints = PointHistory::whereIn('account_id', array_values($accountMap))
            ->where(function ($q) {
                $q->where('year', '<', $this->year)
                    ->orWhere(function ($q2) {
                        $q2->where('year', $this->year)
                            ->where('month', '<', $this->month);
                    });
            })
            ->selectRaw('account_id, SUM(points) as total')
            ->groupBy('account_id')
            ->pluck('total', 'account_id')
            ->toArray();

        $divider = $this->parseAmount($this->settings['point_divider'] ?? 100000);
        if ($divider <= 0) {
            $divider = 100000;
        }

        $batchPh        = [];
        $anomalyRecords = [];
        $accountsToReset = [];

        foreach ($customers as $customer) {
            $accNo = $customer['account_number'] ?? ($customer['ac_id'] ?? null);
            $accId = $accountMap[$accNo] ?? null;
            if (!$accId)
                continue;

            $currAmt     = $this->parseAmount($customer['avgbal_tab'] ?? ($customer['avg_balance'] ?? 0));
            $prevAmt     = (float) ($prevAmounts[$accId] ?? 0);
            $hasPrev     = isset($prevAmounts[$accId]);
            $openingDate = $customer['account_opening_date'] ?? null;

            $inactivMarker = $customer['inactiv_marker'] ?? ($customer['inactivMarker'] ?? null);
            $excludeFlag   = $customer['exclude_flag']   ?? ($customer['excludeFlag']   ?? null);
            $confiFlag     = $customer['confi_flag']     ?? ($customer['confiFlag']     ?? null);

            $isRestricted = ($inactivMarker !== null && $inactivMarker !== '' && $inactivMarker !== 'N')
                || ($excludeFlag !== null && $excludeFlag !== '' && $excludeFlag !== 'N')
                || ($confiFlag   !== null && $confiFlag   !== '' && $confiFlag   !== 'N');

            // --- Anomaly Detection: ETB with empty opening date and no prior month data ---
            if ($this->type === 'etb') {
                $hasMonthBefore = isset($existingMap[$accId][$prevYear][$prevMonth]);
                if (empty($openingDate) && !$hasMonthBefore) {
                    $anomalyRecords[] = [
                        'cif'            => $customer['cif'] ?? null,
                        'account_number' => $accNo,
                        'name'           => $customer['name'] ?? null,
                        'email'          => $customer['email'] ?? null,
                        'description'    => "Anomaly: Empty account_opening_date and no previous month data for ETB.",
                        'avg_balance'    => $currAmt,
                        'year'           => $this->year,
                        'month'          => $this->month,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }

            // --- Point Calculation ---
            $phType   = PointHistory::POINT_TYPE_EARN;
            $typeText = 'BERTAMBAH';
            $points   = 0;
            $status   = $customer['status'] ?? null;

            if ($this->month === $baseMonth && $this->year === $baseYear) {
                // Base period: no previous data to compare — always 0, record as EARN for audit trail
                $phType   = PointHistory::POINT_TYPE_EARN;
                $typeText = 'BERTAMBAH';
                $points   = 0;

            } elseif ($isRestricted) {
                // Account is inactive / excluded / confi — revoke all active tickets
                $participant = $participants[$accId] ?? null;
                $pid         = $participant?->id;
                $activePoints = $pid ? (int) ($activeTicketPoints[$pid] ?? 0) : 0;

                $points = $activePoints > 0 ? -$activePoints : 0;

                if ($pid && $activePoints > 0) {
                    $accountsToReset[] = $pid;
                }

                $phType   = PointHistory::POINT_TYPE_RESET;
                $typeText = 'RESET';
                $status   = 'RESET';

            } elseif ($this->type === 'etb' && !$hasPrev && empty($openingDate)) {
                // ETB with no previous data AND no opening date: truly unknown — skip tickets,
                // but still write a zero-point EARN record for audit trail.
                $phType   = PointHistory::POINT_TYPE_EARN;
                $typeText = 'BERTAMBAH';
                $points   = 0;

            } else {
                $growth = $currAmt - $prevAmt;

                if ($growth < 0) {
                    $threshold = (float) ($this->settings['threshold_reduction_balance'] ?? 100000);
                    
                    if (abs($growth) > $threshold) {
                        // Negative growth exceeds threshold → expire/reset all accumulated points
                        $participant = $participants[$accId] ?? null;
                        $pid         = $participant?->id;
                        $ledgerSum   = (int) ($currentLedgerPoints[$accId] ?? 0);
                        $points      = $ledgerSum > 0 ? -$ledgerSum : 0;

                        $phType   = PointHistory::POINT_TYPE_EXPIRED;
                        $typeText = 'BERKURANG';

                        if ($pid) {
                            $accountsToReset[] = $pid;
                        }
                    } else {
                        // Negative growth but within threshold (e.g. admin fees)
                        // Do not reset points. Just earn 0 points for this month.
                        $phType   = PointHistory::POINT_TYPE_EARN;
                        $typeText = 'BERTAMBAH';
                        $points   = 0;
                    }
                } elseif ($growth > 0) {
                    // Positive growth → award points
                    $openingPoint = 0;
                    if ($this->type === 'ntb') {
                        $openingBalance = $this->parseAmount($customer['account_opening_balance'] ?? 0);
                        $minOpening     = (float) ($this->settings['min_opening_balance'] ?? 500000);
                        $basePoint      = (int) ($this->settings['base_point_ntb'] ?? 10);
                        $openingPoint   = $openingBalance >= $minOpening ? $basePoint : 0;
                    }

                    $points = (int) floor($growth / $divider) + $openingPoint;
                }
                // growth === 0: keep $points = 0, type = EARN (no change recorded)
            }

            // Build stable unique key (one SYSTEM record per account/month/year regardless of type changes on re-run)
            $batchKey  = "{$accId}-{$this->month}-{$this->year}";
            $uniqueKey = "ph_sys_{$accId}_{$this->month}_{$this->year}";

            $batchPh[$batchKey] = [
                'account_id'  => $accId,
                'amount'      => $currAmt,
                'month'       => $this->month,
                'year'        => $this->year,
                'points'      => (int) $points,
                'type'        => $phType,
                'description' => "REK {$accNo} {$typeText} " . abs((int) $points) . " KUPON",
                'source'      => 'SYSTEM',
                'unique_key'  => $uniqueKey,
                'created_at'  => $now,
                'updated_at'  => $now,
                'status'      => $status,
            ];
        }

        // --- Persist Point Histories (upsert, also updates `type` on re-run) ---
        if (!empty($batchPh)) {
            PointHistory::upsert(
                array_values($batchPh),
                ['unique_key'],
                ['amount', 'points', 'type', 'description', 'updated_at', 'status']
            );
        }

        // --- Persist Anomalies (idempotent: updateOrInsert to prevent duplicates) ---
        if (!empty($anomalyRecords)) {
            foreach ($anomalyRecords as $anomaly) {
                // Remove created_at from the update payload so it doesn't overwrite the original creation time
                $updateData = $anomaly;
                unset($updateData['created_at']);
                
                DB::table('point_history_anomalies')->updateOrInsert(
                    [
                        'account_number' => $anomaly['account_number'],
                        'month'          => $anomaly['month'],
                        'year'           => $anomaly['year'],
                    ],
                    $updateData
                );
            }
        }

        // --- Reset lottery tickets for restricted/negative-growth accounts ---
        if (!empty($accountsToReset)) {
            LotteryTicket::where('event_id', $this->eventId)
                ->whereIn('participant_id', $accountsToReset)
                ->where('status', LotteryTicket::STATUS_ACTIVE)
                ->update([
                    'status'     => LotteryTicket::STATUS_RESET,
                    'updated_at' => $now,
                ]);
        }

        return $batchPh;
    }

    private function processParticipantsBulk(array $customers): array
    {
        $accNos     = array_filter(array_map(fn($c) => $c['account_number'] ?? ($c['ac_id'] ?? null), $customers));
        $accountMap = Account::whereIn('account_number', $accNos)->pluck('id', 'account_number')->toArray();
        $now        = now();

        $upserts = [];
        foreach ($customers as $customer) {
            $accNo = $customer['account_number'] ?? ($customer['ac_id'] ?? null);
            $accId = $accountMap[$accNo] ?? null;
            if (!$accId)
                continue;

            // Use account_id as key to prevent duplicates in the same batch
            $upserts[$accId] = [
                'event_id'                   => $this->eventId,
                'account_id'                 => $accId,
                'participant_name'           => $customer['name'],
                'participant_cif'            => $customer['cif'],
                'participant_account_number' => $accNo,
                'participant_email'          => $customer['email'] ?? null,
                'participant_phone_number'   => $customer['phone_number'] ?? null,
                'status'                     => Participant::STATUS_ACTIVE,
                'created_at'                 => $now,
                'updated_at'                 => $now,
            ];
        }

        if (!empty($upserts)) {
            Participant::upsert(
                array_values($upserts),
                ['event_id', 'account_id'],
                ['participant_name', 'participant_cif', 'participant_account_number', 'participant_email', 'participant_phone_number', 'updated_at']
            );
        }

        // Return refreshed map of account_id => participant_id
        return Participant::where('event_id', $this->eventId)
            ->whereIn('account_id', array_values($accountMap))
            ->pluck('id', 'account_id')
            ->toArray();
    }

    private function parseAmount($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (empty($value)) {
            return 0.0;
        }

        // Handle Indonesian format: 1.234.567,89
        // 1. Remove all dots (thousands separator)
        // 2. Replace comma with dot (decimal separator)
        $clean = str_replace('.', '', (string) $value);
        $clean = str_replace(',', '.', $clean);

        // Strip any remaining non-numeric chars except dot/minus
        $clean = preg_replace('/[^0-9.-]/', '', $clean);

        return (float) $clean;
    }
}
