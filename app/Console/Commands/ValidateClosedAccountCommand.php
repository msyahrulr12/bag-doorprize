<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Account;
use App\Models\Setting;
use App\Models\LotteryTicket;
use DB;
use Carbon\Carbon;

class ValidateClosedAccountCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:validate-closed-account-command
                            {--month-now= : Comma-separated list of current months (e.g. 1,2,3)}
                            {--year-now= : The year of the current months (e.g. 2026). If omitted, defaults to year of each month}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate accounts closed in T24 sequentially and deactivate their active lottery tickets.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $monthNowInput = $this->option('month-now');
        $yearNowInput = $this->option('year-now');

        $this->info("==================================================");
        $this->info("         Validate Closed Account Command          ");
        $this->info("==================================================");

        // Fetch base_month and base_year settings
        $baseYear = (int) (Setting::where('key', 'base_comparison_year')->first()->value ?? 2026);
        $baseMonth = (int) (Setting::where('key', 'base_comparison_month')->first()->value ?? 1);

        // Calculate base settings +1 month
        $startMonth = $baseMonth === 12 ? 1 : $baseMonth + 1;
        $startYear = $baseMonth === 12 ? $baseYear + 1 : $baseYear;

        $this->info("Base Period Settings: {$baseMonth}/{$baseYear}");
        $this->info("Validation Threshold Period (+1 month): {$startMonth}/{$startYear}");
        $this->info("--------------------------------------------------");

        // Determine the end period
        if ($monthNowInput) {
            $months = array_filter(array_map('intval', explode(',', $monthNowInput)));
            $defaultYear = $yearNowInput ? (int) $yearNowInput : (int) date('Y');

            $endMonth = max($months);
            $endYear = $defaultYear;
        } else {
            // Auto-detect the maximum period from the database
            $maxPeriod = DB::table('point_histories')
                ->select('month', 'year')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->first();

            if ($maxPeriod) {
                $endMonth = (int) $maxPeriod->month;
                $endYear = (int) $maxPeriod->year;
            } else {
                $endMonth = (int) date('m');
                $endYear = (int) date('Y');
            }
        }

        // Generate all sequential monthly periods from start to end without gaps
        $periodsToProcess = [];
        $current = Carbon::create($startYear, $startMonth, 1)->startOfMonth();
        $endLimit = Carbon::create($endYear, $endMonth, 1)->startOfMonth();

        if ($current->gt($endLimit)) {
            $this->error("The calculated start period ({$startMonth}/{$startYear}) is after the target end period ({$endMonth}/{$endYear}).");
            return Command::FAILURE;
        }

        while ($current->lte($endLimit)) {
            $periodsToProcess[] = [
                'month' => (int) $current->month,
                'year' => (int) $current->year
            ];
            $current->addMonth();
        }

        $this->info("Found " . count($periodsToProcess) . " period(s) to process sequentially:");
        foreach ($periodsToProcess as $p) {
            $this->line(" - " . $p['month'] . "/" . $p['year']);
        }
        $this->info("--------------------------------------------------");

        // Setup external DB connection and tables
        $dbT24 = DB::connection('db_core_t24');
        $tableNtb = env('DB_CORE_T24_TABLE_NTB', 'undian_ntb');
        $tableEtb = env('DB_CORE_T24_TABLE_ETB', 'undian_etb');

        // Dynamically determine the account number column name from tables
        $ntbFirst = (array) $dbT24->table($tableNtb)->first();
        $etbFirst = (array) $dbT24->table($tableEtb)->first();

        $ntbCol = isset($ntbFirst['account_number']) ? 'account_number' : (isset($ntbFirst['ac_id']) ? 'ac_id' : 'ac_id');
        $etbCol = isset($etbFirst['account_number']) ? 'account_number' : (isset($etbFirst['ac_id']) ? 'ac_id' : 'ac_id');

        $this->info("Detected T24 NTB Column: '{$ntbCol}' | ETB Column: '{$etbCol}'");
        $this->info("--------------------------------------------------");

        foreach ($periodsToProcess as $period) {
            $monthNow = $period['month'];
            $yearNow = $period['year'];

            $this->info("");
            $this->info("Processing Period: " . $monthNow . "/" . $yearNow);
            $this->info("--------------------------------------------------");
            
            // Calculate month and year before
            $monthBefore = $monthNow == 1 ? 12 : $monthNow - 1;
            $yearBefore = $monthNow == 1 ? $yearNow - 1 : $yearNow;

            $this->line("Month Before: " . $monthBefore . "/" . $yearBefore);

            // Format dates as YYYY-MM-DD
            $dateBefore = Carbon::create($yearBefore, $monthBefore, 1)->endOfMonth()->format('Y-m-d');
            $dateNow = Carbon::create($yearNow, $monthNow, 1)->endOfMonth()->format('Y-m-d');

            $this->line("T24 file_date now: " . $dateNow . " | file_date before: " . $dateBefore);

            // Fetch previous month accounts from T24
            $prevNtb = $dbT24->table($tableNtb)->where('file_date', $dateBefore)->pluck($ntbCol)->filter()->unique()->toArray();
            $prevEtb = $dbT24->table($tableEtb)->where('file_date', $dateBefore)->pluck($etbCol)->filter()->unique()->toArray();
            $prevAccounts = array_unique(array_merge($prevNtb, $prevEtb));

            $this->line("Accounts in NTB/ETB before: " . count($prevAccounts));

            if (empty($prevAccounts)) {
                $this->warn("No accounts found in T24 for baseline date {$dateBefore}. Skipping this period.");
                $this->info("--------------------------------------------------");
                continue;
            }

            // Fetch current month accounts from T24
            $currNtb = $dbT24->table($tableNtb)->where('file_date', $dateNow)->pluck($ntbCol)->filter()->unique()->toArray();
            $currEtb = $dbT24->table($tableEtb)->where('file_date', $dateNow)->pluck($etbCol)->filter()->unique()->toArray();
            $currAccounts = array_unique(array_merge($currNtb, $currEtb));

            $this->line("Accounts in NTB/ETB now: " . count($currAccounts));

            // Find accounts that were in previous month but are missing now
            $missingAccountNumbers = array_diff($prevAccounts, $currAccounts);
            $missingCount = count($missingAccountNumbers);

            $this->line("Missing accounts (present in previous but absent now): " . $missingCount);

            if ($missingCount === 0) {
                $this->info("No missing accounts for this period.");
                $this->info("--------------------------------------------------");
                continue;
            }

            // Query local active/non-closed accounts matching these numbers
            $accountsToProcess = Account::whereIn('account_number', $missingAccountNumbers)
                ->where('status', '!=', Account::STATUS_CLOSED)
                ->get();

            $localCount = $accountsToProcess->count();
            $this->info("Matching active/non-closed local accounts found to close: " . $localCount);

            if ($localCount === 0) {
                $this->info("No local accounts need updating for this period.");
                $this->info("--------------------------------------------------");
                continue;
            }

            // Update in a transaction for data integrity
            DB::beginTransaction();
            try {
                $closedCount = 0;
                $ticketsResetCount = 0;

                foreach ($accountsToProcess as $account) {
                    // Update account status to CLOSED
                    $account->update([
                        'status' => Account::STATUS_CLOSED,
                        'updated_at' => now(),
                    ]);
                    $closedCount++;

                    // Put their active tickets to RESET/INACTIVE
                    $ticketsReset = LotteryTicket::whereHas('participant', function($q) use ($account) {
                        $q->where('account_id', $account->id);
                    })->where('status', LotteryTicket::STATUS_ACTIVE)
                      ->update([
                          'status' => LotteryTicket::STATUS_RESET,
                          'updated_at' => now(),
                      ]);

                    $ticketsResetCount += $ticketsReset;
                    
                    $this->line(" -> Account [{$account->account_number}] (ID: {$account->id}) closed. Reset {$ticketsReset} ticket(s).");
                }

                DB::commit();
                $this->info("Successfully closed {$closedCount} accounts and reset {$ticketsResetCount} active lottery tickets for {$monthNow}/{$yearNow}.");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Error updating accounts/tickets: " . $e->getMessage());
            }

            $this->info("--------------------------------------------------");
        }

        $this->info("✓ Validate Closed Account Command execution finished.");
        return Command::SUCCESS;
    }
}
