<?php

namespace App\Jobs;

use App\Events\PointHistoryProcessed;
use App\Models\Account;
use App\Models\Customer;
use App\Models\LotteryTicket;
use App\Models\Participant;
use App\Models\PointHistory;
use DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Log;

class ProcessPointHistory implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private array $chunk,
        private array $header,
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
        Log::info(sprintf('Running Job Process Point History (%s) at %s', $this->type, now()->toDateTimeString()));
        $rows = [];
        foreach ($this->chunk as $row) {
            if (count($this->header) !== count($row)) {
                continue;
            }
            $row = array_map('trim', $row);
            $rows[] = array_combine($this->header, $row);
        }

        if (empty($rows)) {
            return;
        }

        Log::info(sprintf('Processing Point History (%s)...', $this->type));
        DB::transaction(function () use ($rows) {
            // 1. Process Customers
            $this->processCustomers($rows);

            // 2. Process Accounts
            $this->processAccounts($rows);

            // 3. Process Point Histories (capturing the data for further processing)
            $pointHistories = $this->processPointHistories($rows);

            if ($this->eventId) {
                // 4. Process Participants
                $participantMap = $this->processParticipants($rows);

                // 5. Process Lottery Tickets
                event(new PointHistoryProcessed($pointHistories, $participantMap, $this->eventId, $this->month, $this->year));
            }
        });

        Log::info('Job Process Point History finished at ' . now()->toDateTimeString());
    }

    private function processCustomers(array $rows): void
    {
        Log::info('Processing Customers...');
        $customers = [];
        foreach ($rows as $row) {
            $branchCodeCif = $row['cus_open_branch'] ?? null;
            $branchIdCif = $this->branches[$branchCodeCif] ?? null;
            if (!$branchIdCif) {
                continue;
            }

            $customers[$row['cif']] = [
                'branch_id' => $branchIdCif,
                'name' => $row['name'],
                'cif' => $row['cif'],
                'email' => $row['email'],
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Log::info('Total Data Customers: ' . count($customers));

        if (!empty($customers)) {
            Customer::upsert(
                array_values($customers),
                ['cif'],
                ['name', 'email', 'branch_id', 'updated_at']
            );
        }

        Log::info('Customers processed');
    }

    private function processAccounts(array $rows): void
    {
        Log::info('Processing Accounts...');
        // Fetch customer mapping for this chunk
        $cifs = array_unique(array_column($rows, 'cif'));
        $customerMap = Customer::whereIn('cif', $cifs)->pluck('id', 'cif')->toArray();

        $accounts = [];
        foreach ($rows as $row) {
            $customerId = $customerMap[$row['cif']] ?? null;
            $branchIdAccount = $this->branches[$row['acc_open_branch']] ?? null;
            $productId = $this->products[$row['jenis_rekening']] ?? null;

            if (!$customerId || !$branchIdAccount || !$productId) {
                continue;
            }

            $accounts[$row['account_number'] ?? $row['ac_id']] = [
                'customer_id' => $customerId,
                'branch_id' => $branchIdAccount,
                'product_id' => $productId,
                'account_number' => $row['account_number'] ?? $row['ac_id'],
                'account_type' => $row['jenis_rekening'],
                'account_opening_date' => $row['account_opening_date'] ?? null,
                'account_opening_balance' => $row['account_opening_balance'] ?? 0,
                'current_balance' => ($row['avgbal_tab'] ?? $row['avg_balance']) ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Log::info('Total Data Accounts: ' . count($accounts));

        if (!empty($accounts)) {
            Account::upsert(
                array_values($accounts),
                ['account_number'],
                ['customer_id', 'branch_id', 'product_id', 'account_opening_date', 'account_opening_balance', 'current_balance', 'updated_at']
            );
        }

        Log::info('Accounts processed');
    }

    private function processPointHistories(array $rows): array
    {
        Log::info('Processing Point Histories...');

        // 1. Determine previous month/year
        $prevMonth = $this->month - 1;
        $prevYear = $this->year;
        if ($prevMonth === 0) {
            $prevMonth = 12;
            $prevYear--;
        }

        // 2. Fetch account mapping for this chunk
        $arrCol = array_column($rows, 'account_number') ? array_column($rows, 'account_number') : array_column($rows, 'ac_id');
        $accountNumbers = array_unique($arrCol);
        $accountMap = Account::whereIn('account_number', $accountNumbers)->pluck('id', 'account_number')->toArray();

        // 3. Fetch previous month's amount from PointHistory
        $previousAmounts = PointHistory::whereIn('account_id', array_values($accountMap))
            ->where('month', $prevMonth)
            ->where('year', $prevYear)
            ->pluck('amount', 'account_id')
            ->toArray();

        $pointHistories = [];
        $accountsToReset = [];
        $divider = (float) ($this->settings['point_divider'] ?? 100000);

        foreach ($rows as $row) {
            $accountId = $accountMap[$row['account_number'] ?? $row['ac_id']] ?? null;
            if (!$accountId) {
                continue;
            }

            $currentAmount = (float) (($row['avgbal_tab'] ?? $row['avg_balance']) ?? 0);
            $prevAmount = $previousAmounts[$accountId] ?? 0; // Default to 0 if no history
            $growth = $currentAmount - $prevAmount;

            // Trend check: if current - previous is minus (Step Id: 200/314)
            $thresholdReductionBalance = $this->settings['threshold_reduction_balance'] ?? 100000;
            $isNegativeTrend = (($growth + $thresholdReductionBalance) < 0);

            $type = PointHistory::POINT_TYPE_EARN;
            if ($isNegativeTrend) {
                $participant = Participant::where('account_id', $accountId)->first();
                $points = $participant ? -(LotteryTicket::where('participant_id', $participant->id)->where('status', LotteryTicket::STATUS_ACTIVE)->sum('total_points')) : 0;
                $accountsToReset[] = $accountId;
                Log::info(sprintf("Account %s has negative growth (%s). Resetting points.", $row['account_number'] ?? $row['ac_id'], $growth));
                $type = PointHistory::POINT_TYPE_EXPIRED;
            } elseif ($growth == 0) {
                $points = 0;
            } else {
                // Growth is positive
                // Point calculation based on type (Step Id: 283/314)
                $openingPoints = 0;
                if ($this->type === 'ntb' && (float) ($row['account_opening_balance'] ?? 0) >= ($this->settings['min_opening_balance'] ?? 500000)) {
                    $openingPoints = $this->settings['base_point_ntb'] ?? 10;
                }
                $points = floor($growth / $divider);
                $points += $openingPoints;
            }

            $pointHistories[] = [
                'account_id' => $accountId,
                'amount' => $currentAmount,
                'month' => $this->month,
                'year' => $this->year,
                'points' => (int) $points,
                'type' => $type,
                'description' => "Monthly point earn for {$this->month}/{$this->year} ({$this->type}) - Growth: " . number_format($growth, 0),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Log::info('Total Data PointHistories: ' . count($pointHistories));

        if (!empty($pointHistories)) {
            PointHistory::upsert(
                $pointHistories,
                ['account_id', 'month', 'year'],
                ['amount', 'points', 'description', 'updated_at']
            );
        }

        // 4. Handle resetting lottery tickets if any
        if ($this->eventId && !empty($accountsToReset)) {
            $participantIds = Participant::where('event_id', $this->eventId)
                ->whereIn('account_id', $accountsToReset)
                ->pluck('id')
                ->toArray();

            if (!empty($participantIds)) {
                LotteryTicket::where('event_id', $this->eventId)
                    ->whereIn('participant_id', $participantIds)
                    ->update([
                        'status' => LotteryTicket::STATUS_RESET,
                        'description' => 'Reset due to negative balance trend'
                    ]);
                Log::info('Reset lottery tickets for ' . count($participantIds) . ' participants');
            }
        }

        Log::info('PointHistories processed');

        return $pointHistories;
    }

    private function processParticipants(array $rows): array
    {
        Log::info('Processing Participants...');

        // Handle both 'account_number' and 'ac_id' fields
        $accountNumbers = [];
        foreach ($rows as $row) {
            $accountNumber = $row['account_number'] ?? $row['ac_id'] ?? null;
            if ($accountNumber) {
                $accountNumbers[] = $accountNumber;
            }
        }
        $accountNumbers = array_unique($accountNumbers);
        $accountMap = Account::whereIn('account_number', $accountNumbers)->get()->keyBy('account_number');

        $participantIds = [];
        foreach ($rows as $row) {
            $account = $accountMap[$row['account_number'] ?? $row['ac_id']] ?? null;
            if (!$account)
                continue;

            $participant = Participant::updateOrCreate([
                'event_id' => $this->eventId,
                'account_id' => $account->id,
            ], [
                'participant_name' => $row['name'],
                'participant_cif' => $row['cif'],
                'participant_account_number' => $row['account_number'] ?? $row['ac_id'],
                'participant_email' => $row['email'],
                'participant_phone_number' => $row['phone_number'] ?? '',
                'status' => 'active',
                'updated_at' => now(),
            ]);

            $participantIds[$account->id] = $participant->id;
        }

        Log::info('Participants processed: ' . count($participantIds));
        return $participantIds;
    }
}
