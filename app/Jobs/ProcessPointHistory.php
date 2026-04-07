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
use Carbon\Carbon;

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
                'branch_id' => $branchId,
                'name' => $customer['name'],
                'cif' => $customer['cif'],
                'email' => isset($customer['email']) ? $customer['email'] : null,
                'status' => Customer::STATUS_ACTIVE,
                'date_of_birth' => (isset($customer['date_of_birth']) && $customer['date_of_birth'] !== '') ? $customer['date_of_birth'] : null,
                'created_at' => $now,
                'updated_at' => $now,
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
            $branchId = $this->branches[trim($customer['acc_open_branch'] ?? '')] ?? null;
            $productId = $this->products[trim($customer['jenis_rekening'] ?? '')] ?? null;
            $accNo = $customer['account_number'] ?? ($customer['ac_id'] ?? null);

            if (!$customerId || !$branchId || !$productId || !$accNo)
                continue;

            $inactivMarker = $customer['inactiv_marker'] ?? ($customer['inactivMarker'] ?? null);
            $excludeFlag = $customer['exclude_flag'] ?? ($customer['excludeFlag'] ?? null);
            $confiFlag = $customer['confi_flag'] ?? ($customer['confiFlag'] ?? null);

            $accountStatus = null;
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
                'customer_id' => $customerId,
                'branch_id' => $branchId,
                'product_id' => $productId,
                'account_number' => $accNo,
                'account_type' => $customer['jenis_rekening'],
                'account_opening_date' => $customer['account_opening_date'] ?? null,
                'account_opening_balance' => $this->parseAmount($customer['account_opening_balance'] ?? 0),
                'current_balance' => $this->parseAmount($customer['avgbal_tab'] ?? ($customer['avg_balance'] ?? 0)),
                'created_at' => $now,
                'updated_at' => $now,
                'status' => $accountStatus,
            ];
        }

        if (!empty($upserts)) {
            Account::upsert(array_values($upserts), ['account_number'], ['customer_id', 'branch_id', 'product_id', 'account_opening_date', 'account_opening_balance', 'current_balance', 'updated_at', 'status']);
        }
    }

    private function processPointHistoriesBulk(array $customers): array
    {
        $prevMonth = $this->month - 1 ?: 12;
        $prevYear = ($this->month === 1) ? $this->year - 1 : $this->year;
        $existingMap = [];

        $baseYear = (int) ($this->settings['base_comparison_year'] ?? 2026);
        $baseMonth = (int) ($this->settings['base_comparison_month'] ?? 1);

        $accNos = array_filter(array_map(fn($c) => $c['account_number'] ?? ($c['ac_id'] ?? null), $customers));
        $accountMap = Account::whereIn('account_number', $accNos)->pluck('id', 'account_number')->toArray();
        $now = now();

        if ($this->type === 'etb') {
            // Check existing records in range [base, prev] across ANY type to feed anomaly detection. 
            $existing = PointHistory::whereIn('account_id', array_values($accountMap))
                ->where('year', '>=', $baseYear)
                ->where('year', '<=', $prevYear)
                ->get(['account_id', 'month', 'year']);

            $existingMap = [];
            foreach ($existing as $rec) {
                $existingMap[$rec->account_id][$rec->year][$rec->month] = true;
            }
        }

        // Fetch prev-month amounts from EARN records only.
        // Using EXPIRED amounts for growth comparison would produce wrong (usually negative) growth.
        $prevAmounts = PointHistory::whereIn('account_id', array_values($accountMap))
            ->where('month', $prevMonth)
            ->where('year', $prevYear)
            ->where('description', '!=', 'BASE COMPARISON DATA (GAP FILL)') // Ignore phantom zero-balance records
            ->orderBy('amount', 'desc') 
            ->get(['amount', 'account_id'])
            ->pluck('amount', 'account_id')
            ->toArray();

        $participants = Participant::whereIn('account_id', array_values($accountMap))
            ->where('event_id', $this->eventId)->get(['id', 'account_id'])->keyBy('account_id');

        $activeTicketPoints = [];
        if ($participants->isNotEmpty()) {
            $activeTicketPoints = LotteryTicket::whereIn('participant_id', $participants->pluck('id'))
                ->where('status', LotteryTicket::STATUS_ACTIVE)
                ->selectRaw('participant_id, SUM(total_points) as total')->groupBy('participant_id')->pluck('total', 'participant_id')->toArray();
        }

        $batchPh = [];
        $anomalyRecords = [];
        $accountsToReset = [];
        $now = now();
        
        $divider = $this->parseAmount($this->settings['point_divider'] ?? 100000);
        $threshold = $this->parseAmount($this->settings['threshold_reduction_balance'] ?? 100000);

        // Fetch current cumulative points from ledger to ensure a full reset on expiration
        // Exclude current month/year to make it re-run safe for the same batch
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

        foreach ($customers as $customer) {
            $accNo = $customer['account_number'] ?? ($customer['ac_id'] ?? null);
            $accId = $accountMap[$accNo] ?? null;
            if (!$accId)
                continue;

            $currAmt = $this->parseAmount($customer['avgbal_tab'] ?? ($customer['avg_balance'] ?? 0));
            $prevAmt = (float) ($prevAmounts[$accId] ?? 0);
            $hasPrev = isset($prevAmounts[$accId]);

            // account_opening_date is used to detect new ETB accounts belonging to new customers
            // who also registered other accounts. If present, points are calculated normally.
            $openingDate = $customer['account_opening_date'] ?? null;

            // Anomaly Detection: ETB with empty opening date and no month before data
            if ($this->type === 'etb') {
                $hasMonthBefore = isset($existingMap[$accId][$prevYear][$prevMonth]);

                if (empty($openingDate) && !$hasMonthBefore) {
                    $anomalyRecords[] = [
                        'cif' => $customer['cif'] ?? null,
                        'account_number' => $accNo,
                        'name' => $customer['name'] ?? null,
                        'email' => $customer['email'] ?? null,
                        'description' => "Anomaly: Empty account_opening_date and no previous month data for ETB.",
                        'avg_balance' => $currAmt,
                        'year' => $this->year,
                        'month' => $this->month,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            $growth = $currAmt - $prevAmt;

            $status = $customer['status'] ?? null;
            $inactivMarker = $customer['inactiv_marker'] ?? ($customer['inactivMarker'] ?? null);
            $excludeFlag = $customer['exclude_flag'] ?? ($customer['excludeFlag'] ?? null);
            $confiFlag = $customer['confi_flag'] ?? ($customer['confiFlag'] ?? null);

            $type = PointHistory::POINT_TYPE_EARN;
            $typeText = "BERTAMBAH";
            $points = 0;

            // Base period: no comparison possible, points are always 0
            if ($this->month === $baseMonth && $this->year === $baseYear) {
                $points = 0;
            } elseif (($inactivMarker !== null && $inactivMarker !== '' && $inactivMarker !== 'N') || ($excludeFlag !== null && $excludeFlag !== '' && $excludeFlag !== 'N') || ($confiFlag !== null && $confiFlag !== '' && $confiFlag !== 'N')) {
                $pid = $participants[$accId]->id ?? null;
                $activePoints = $pid ? ($activeTicketPoints[$pid] ?? 0) : 0;

                if ($activePoints > 0) {
                    $points = -$activePoints;
                    if ($pid) {
                        $accountsToReset[] = $pid;
                    }
                } else {
                    $points = 0;
                }

                $type = PointHistory::POINT_TYPE_RESET;
                $status = 'RESET';
                $typeText = "RESET";
            } elseif ($this->type === 'etb' && !$hasPrev && empty($openingDate)) {
                // ETB with no previous data AND no opening date: truly unknown account, skip points.
                // If opening date IS present, this is a new account for an existing/new customer
                // who also registered accounts in NTB — fall through to normal calculation.
                $points = 0;
            } else {
                // If growth is negative, we reset all points. 
                // Removed threshold check to allow any decrease to trigger a reset as requested by client.
                if ($growth < 0) {
                    $pid = $participants[$accId]->id ?? null;
                    // Reset ALL points in ledger for this account
                    $ledgerSum = (int) ($currentLedgerPoints[$accId] ?? 0);
                    $points = $ledgerSum > 0 ? -$ledgerSum : 0;
                    
                    $type = PointHistory::POINT_TYPE_EXPIRED;
                    $typeText = "BERKURANG";
                    if ($pid)
                        $accountsToReset[] = $pid;
                } elseif ($growth > 0) {
                    $openingPoint = 0;
                    if ($this->type === 'ntb') {
                        $openingPoint = $this->parseAmount($customer['account_opening_balance'] ?? 0) >= ($this->settings['min_opening_balance'] ?? 500000) ? (int) ($this->settings['base_point_ntb'] ?? 10) : 0;
                    }

                    $points = (int) floor($growth / $divider) + $openingPoint;
                }
            }

            // Check if the processed

            // Create a unique key for the batch based on the upsert columns
            // For SYSTEM imports, the key is stable to enable upsert idempotency.
            // We removed {type} from the key to ensure only one system record exists per account/month/year,
            // even if the type changes during a re-run.
            $batchKey = "{$accId}-{$this->month}-{$this->year}";
            $uniqueKey = "ph_sys_{$accId}_{$this->month}_{$this->year}";
            $batchPh[$batchKey] = [
                'account_id' => $accId,
                'amount' => $currAmt,
                'month' => $this->month,
                'year' => $this->year,
                'points' => (int) $points,
                'type' => $type,
                'description' => "REK {$accNo} {$typeText} " . abs((int) $points) . " KUPON",
                'source' => 'SYSTEM',
                'unique_key' => $uniqueKey,
                'created_at' => $now,
                'updated_at' => $now,
                'status' => $status,
            ];
        }

        if (!empty($batchPh)) {
            PointHistory::upsert(array_values($batchPh), ['unique_key'], ['amount', 'points', 'description', 'updated_at', 'status']);
        }

        if (!empty($anomalyRecords)) {
            DB::table('point_history_anomalies')->insert($anomalyRecords);
        }

        if (!empty($accountsToReset)) {
            LotteryTicket::where('event_id', $this->eventId)
                ->whereIn('participant_id', $accountsToReset)
                ->where('status', LotteryTicket::STATUS_ACTIVE)
                ->update([
                    'status' => LotteryTicket::STATUS_RESET,
                    'updated_at' => $now
                ]);
        }

        return $batchPh;
    }

    private function processParticipantsBulk(array $customers): array
    {
        $accNos = array_filter(array_map(fn($c) => $c['account_number'] ?? ($c['ac_id'] ?? null), $customers));
        $accountMap = Account::whereIn('account_number', $accNos)->pluck('id', 'account_number')->toArray();
        $now = now();

        $upserts = [];
        foreach ($customers as $customer) {
            $accNo = $customer['account_number'] ?? ($customer['ac_id'] ?? null);
            $accId = $accountMap[$accNo] ?? null;
            if (!$accId)
                continue;

            // Use account_id as key to prevent duplicates in the same batch
            $upserts[$accId] = [
                'event_id' => $this->eventId,
                'account_id' => $accId,
                'participant_name' => $customer['name'],
                'participant_cif' => $customer['cif'],
                'participant_account_number' => $accNo,
                'participant_email' => isset($customer['email']) ? $customer['email'] : null,
                'participant_phone_number' => isset($customer['phone_number']) ? $customer['phone_number'] : null,
                'status' => Participant::STATUS_ACTIVE,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($upserts)) {
            Participant::upsert(array_values($upserts), ['event_id', 'account_id'], ['participant_name', 'participant_cif', 'participant_account_number', 'participant_email', 'participant_phone_number', 'updated_at']);
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
        // 1. Remove all dots (thousands)
        // 2. Replace comma with dot (decimal)
        $clean = str_replace('.', '', $value);
        $clean = str_replace(',', '.', $clean);

        // If it still contains non-numeric chars (except dot/minus), try to strip them
        $clean = preg_replace('/[^0-9.-]/', '', $clean);

        return (float) $clean;
    }
}
