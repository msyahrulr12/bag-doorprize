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

    public $timeout = 300;

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
        Log::info(sprintf('Running Job Process Point History (%s) at %s', $this->type, now()->toDateTimeString()));

        if (empty($this->customers)) {
            return;
        }

        // 0. Pre-convert customers to associative arrays once
        $customers = array_map(function ($customer) {
            return (array) $customer;
        }, $this->customers);

        Log::info(sprintf('Processing Point History (%s) for %d records...', $this->type, count($customers)));

        DB::transaction(function () use ($customers) {
            // 1. Process Customers
            $this->processCustomers($customers);

            // 2. Process Accounts
            $this->processAccounts($customers);

            // 3. Process Point Histories
            $pointHistories = $this->processPointHistories($customers);

            if ($this->eventId) {
                // 4. Process Participants
                $participantMap = $this->processParticipants($customers);

                // 5. Process Lottery Tickets
                event(new PointHistoryProcessed($pointHistories, $participantMap, $this->eventId, $this->month, $this->year));
            }
        });

        Log::info('Job Process Point History finished at ' . now()->toDateTimeString());
    }

    private function processCustomers(array $customers): void
    {
        Log::info('Processing Customers...');
        $dataCustomers = [];
        foreach ($customers as $customer) {
            $branchCodeCif = $customer['cus_open_branch'] ?? null;
            $branchIdCif = $this->branches[$branchCodeCif] ?? null;
            if (!$branchIdCif) {
                continue;
            }

            $dataCustomers[$customer['cif']] = [
                'branch_id' => $branchIdCif,
                'name' => $customer['name'],
                'cif' => $customer['cif'],
                'email' => $customer['email'],
                'status' => Customer::STATUS_ACTIVE,
                'date_of_birth' => isset($customer['date_of_birth']) && $customer['date_of_birth'] !== '' ? $customer['date_of_birth'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Log::info('Total Data Customers to upsert: ' . count($dataCustomers));

        if (!empty($dataCustomers)) {
            Customer::upsert(
                array_values($dataCustomers),
                ['cif'],
                ['name', 'email', 'branch_id', 'updated_at']
            );
        }

        Log::info('Customers processed');
    }

    private function processAccounts(array $customers): void
    {
        Log::info('Processing Accounts...');

        $cifs = array_unique(array_column($customers, 'cif'));
        $customerMap = Customer::whereIn('cif', $cifs)->pluck('id', 'cif')->toArray();

        $accounts = [];
        foreach ($customers as $customer) {
            $customerId = $customerMap[$customer['cif']] ?? null;
            $branchIdAccount = $this->branches[$customer['acc_open_branch']] ?? null;
            $productId = $this->products[$customer['jenis_rekening']] ?? null;

            if (!$customerId || !$branchIdAccount || !$productId) {
                continue;
            }

            $accNo = $customer['account_number'] ?? ($customer['ac_id'] ?? null);
            if (!$accNo)
                continue;

            $accounts[$accNo] = [
                'customer_id' => $customerId,
                'branch_id' => $branchIdAccount,
                'product_id' => $productId,
                'account_number' => $accNo,
                'account_type' => $customer['jenis_rekening'],
                'account_opening_date' => $customer['account_opening_date'] ?? null,
                'account_opening_balance' => (float) ($customer['account_opening_balance'] ?? 0),
                'current_balance' => (float) (($customer['avgbal_tab'] ?? ($customer['avg_balance'] ?? 0))),
                'created_at' => now(),
                'updated_at' => now(),
                'status' => Account::STATUS_ACTIVE,
            ];
        }

        Log::info('Total Data Accounts to upsert: ' . count($accounts));

        if (!empty($accounts)) {
            Account::upsert(
                array_values($accounts),
                ['account_number'],
                ['customer_id', 'branch_id', 'product_id', 'account_opening_date', 'account_opening_balance', 'current_balance', 'updated_at']
            );
        }

        Log::info('Accounts processed');
    }

    private function processPointHistories(array $customers): array
    {
        Log::info('Processing Point Histories...');

        $prevMonth = $this->month - 1;
        $prevYear = $this->year;
        if ($prevMonth === 0) {
            $prevMonth = 12;
            $prevYear--;
        }

        $accountNumbers = array_filter(array_unique(array_map(fn($c) => $c['account_number'] ?? ($c['ac_id'] ?? null), $customers)));
        $accountMap = Account::whereIn('account_number', $accountNumbers)->pluck('id', 'account_number')->toArray();

        $previousAmounts = PointHistory::whereIn('account_id', array_values($accountMap))
            ->where('month', $prevMonth)
            ->where('year', $prevYear)
            ->pluck('amount', 'account_id')
            ->toArray();

        // Optimize: Bulk fetch participants and their active ticket point totals for negative growth reset
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

        $pointHistories = [];
        $accountsToReset = [];
        $divider = (float) ($this->settings['point_divider'] ?? 100000);
        $thresholdReductionBalance = (float) ($this->settings['threshold_reduction_balance'] ?? 100000);

        foreach ($customers as $customer) {
            $accountNumber = $customer['account_number'] ?? ($customer['ac_id'] ?? null);
            $accountId = $accountMap[$accountNumber] ?? null;
            if (!$accountId) {
                continue;
            }

            $currentAmount = (float) (($customer['avgbal_tab'] ?? ($customer['avg_balance'] ?? 0)));
            $hasPrevAmount = isset($previousAmounts[$accountId]);
            $prevAmount = (float) ($previousAmounts[$accountId] ?? 0);
            $growth = $currentAmount - $prevAmount;

            $type = PointHistory::POINT_TYPE_EARN;
            $typeText = "BERTAMBAH";
            $points = 0;

            // Rule: Calculate growth ONLY when prev month exists for the account.
            // If prev month doesn't exist, it's base data month -> 0 points for ETB.
            if ($this->type === 'etb' && !$hasPrevAmount) {
                $points = 0;
            } else {
                $isNegativeTrend = ($growth < 0) && (abs($growth) > $thresholdReductionBalance);

                if ($isNegativeTrend) {
                    $participantId = $participants[$accountId]->id ?? null;
                    $points = $participantId ? -(($activeTicketPoints[$participantId] ?? 0)) : 0;
                    Log::info(sprintf("Account %s has negative growth (%s). Resetting points.", $accountNumber, $growth));
                    $type = PointHistory::POINT_TYPE_EXPIRED;
                    $typeText = "BERKURANG";
                    $accountsToReset[$accountId] = [
                        'account_id' => $accountId,
                        'type_text' => $typeText,
                        'points' => $points,
                        'account_number' => $accountNumber,
                        'participant_id' => $participantId,
                    ];
                } elseif ($growth > 0) {
                    $openingPoints = 0;
                    if ($this->type === 'ntb' && (float) ($customer['account_opening_balance'] ?? 0) >= ($this->settings['min_opening_balance'] ?? 500000)) {
                        $openingPoints = (int) ($this->settings['base_point_ntb'] ?? 10);
                    }
                    $points = (int) floor($growth / $divider);
                    $points = max(0, $points) + $openingPoints;
                }
            }

            $pointHistories[] = [
                'account_id' => $accountId,
                'amount' => $currentAmount,
                'month' => $this->month,
                'year' => $this->year,
                'points' => (int) $points,
                'type' => $type,
                'description' => "REK {$accountNumber} {$typeText} " . abs((int) $points) . " KUPON",
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Log::info('Total records for PointHistory: ' . count($pointHistories));

        if (!empty($pointHistories)) {
            foreach ($pointHistories as $ph) {
                PointHistory::updateOrCreate(
                    [
                        'account_id' => $ph['account_id'],
                        'month' => $ph['month'],
                        'year' => $ph['year'],
                        'type' => $ph['type'],
                    ],
                    [
                        'amount' => $ph['amount'],
                        'points' => $ph['points'],
                        'description' => $ph['description'],
                        'updated_at' => $ph['updated_at'],
                    ]
                );
            }
        }

        // 4. Handle resetting lottery tickets if any
        if ($this->eventId && !empty($accountsToReset)) {
            foreach ($accountsToReset as $accountId => $data) {
                if ($data['participant_id']) {
                    LotteryTicket::where('event_id', $this->eventId)
                        ->where('participant_id', $data['participant_id'])
                        ->where('status', LotteryTicket::STATUS_ACTIVE)
                        ->update([
                            'status' => LotteryTicket::STATUS_RESET,
                            'description' => "REK {$data['account_number']} {$data['type_text']} " . abs($data['points']) . " KUPON",
                        ]);
                }
            }
            Log::info('Reset lottery tickets for ' . count($accountsToReset) . ' participants');
        }

        Log::info('PointHistories processed');

        return $pointHistories;
    }

    private function processParticipants(array $customers): array
    {
        Log::info('Processing Participants...');

        $accountNumbers = array_filter(array_unique(array_map(fn($c) => $c['account_number'] ?? ($c['ac_id'] ?? null), $customers)));
        $accountMap = Account::whereIn('account_number', $accountNumbers)->get()->keyBy('account_number');

        $participantIds = [];
        foreach ($customers as $customer) {
            $account = $accountMap[$customer['account_number'] ?? ($customer['ac_id'] ?? null)] ?? null;
            if (!$account)
                continue;

            $participant = Participant::updateOrCreate([
                'event_id' => $this->eventId,
                'account_id' => $account->id,
            ], [
                'participant_name' => $customer['name'],
                'participant_cif' => $customer['cif'],
                'participant_account_number' => $account->account_number,
                'participant_email' => $customer['email'],
                'participant_phone_number' => $customer['phone_number'] ?? '',
                'status' => 'active',
                'updated_at' => now(),
            ]);

            $participantIds[$account->id] = $participant->id;
        }

        Log::info('Participants processed: ' . count($participantIds));
        return $participantIds;
    }
}
