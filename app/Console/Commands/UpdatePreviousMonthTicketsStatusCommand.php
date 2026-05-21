<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LotteryTicket;
use App\Models\Participant;
use App\Models\PointHistory;
use Illuminate\Support\Facades\DB;

class UpdatePreviousMonthTicketsStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-tickets-status
                            {--month-now= : Comma-separated list of current months (e.g. 1,2,3)}
                            {--year-now= : The year of the current months (e.g. 2026). If omitted, defaults to year of each month}
                            {--status=RESET : Target status for previous month\'s tickets (e.g. RESET or INACTIVE)}
                            {--dry-run : Only show stats of what would be updated without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update lottery tickets status for the month before based on current month\'s growth (negative growth).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $statusToSet = $this->option('status');
        $dryRun = $this->option('dry-run');
        $monthsInput = $this->option('month-now');
        $yearInput = $this->option('year-now');

        $this->info("==================================================");
        $this->info("  Lottery Tickets Status Auto-Updater Command     ");
        $this->info("==================================================");
        $this->info("Target Status: " . $statusToSet);
        $this->info("Mode: " . ($dryRun ? "DRY RUN (No database writes)" : "LIVE UPDATE (Writes to database)"));
        $this->info("--------------------------------------------------");

        // Determine which month/year pairs to process
        $periodsToProcess = [];

        if ($monthsInput) {
            $months = array_filter(array_map('intval', explode(',', $monthsInput)));
            $defaultYear = $yearInput ? (int) $yearInput : (int) date('Y');
            foreach ($months as $m) {
                $periodsToProcess[] = [
                    'month' => $m,
                    'year' => $defaultYear
                ];
            }
        } else {
            // Auto-detect available months in point_histories ordered chronologically
            $this->comment("No target months provided. Auto-detecting available periods from point_histories...");
            
            $dbPeriods = DB::table('point_histories')
                ->select('month', 'year')
                ->groupBy('month', 'year')
                ->orderBy('year', 'asc')
                ->orderBy('month', 'asc')
                ->get();

            foreach ($dbPeriods as $period) {
                $periodsToProcess[] = [
                    'month' => (int) $period->month,
                    'year' => (int) $period->year
                ];
            }
        }

        if (empty($periodsToProcess)) {
            $this->error("No months found or specified to process.");
            return Command::FAILURE;
        }

        $this->info("Found " . count($periodsToProcess) . " period(s) to process.");
        $this->info("--------------------------------------------------");

        foreach ($periodsToProcess as $period) {
            $monthNow = $period['month'];
            $yearNow = $period['year'];

            // Calculate the month before
            $monthBefore = $monthNow === 1 ? 12 : $monthNow - 1;
            $yearBefore = $monthNow === 1 ? $yearNow - 1 : $yearNow;

            $this->comment("Processing Period: Current Month: $monthNow/$yearNow | Previous Month: $monthBefore/$yearBefore");

            // 1. Get accounts with negative growth (amount in monthNow < amount in monthBefore)
            $negativeGrowthAccounts = DB::table('point_histories as ph_now')
                ->join('point_histories as ph_before', function ($join) use ($monthBefore, $yearBefore) {
                    $join->on('ph_now.account_id', '=', 'ph_before.account_id')
                         ->where('ph_before.month', '=', $monthBefore)
                         ->where('ph_before.year', '=', $yearBefore);
                })
                ->where('ph_now.month', $monthNow)
                ->where('ph_now.year', $yearNow)
                ->whereRaw('ph_now.amount < ph_before.amount')
                ->pluck('ph_now.account_id');

            $negCount = count($negativeGrowthAccounts);

            if ($negCount === 0) {
                $this->line(" -> Accounts with negative growth: 0. Skipping.");
                $this->line("--------------------------------------------------");
                continue;
            }

            $this->line(" -> Accounts with negative growth: $negCount");

            // 2. Map accounts to participants
            $participantIds = Participant::whereIn('account_id', $negativeGrowthAccounts)->pluck('id');
            $participantCount = count($participantIds);

            if ($participantCount === 0) {
                $this->line(" -> Matching participants found: 0. Skipping.");
                $this->line("--------------------------------------------------");
                continue;
            }

            // 3. Find active tickets for all months before the current target month
            $ticketsQuery = LotteryTicket::whereIn('participant_id', $participantIds)
                ->where(function ($q) use ($monthNow, $yearNow) {
                    $q->where('year', '<', $yearNow)
                      ->orWhere(function ($q2) use ($monthNow, $yearNow) {
                          $q2->where('year', '=', $yearNow)
                             ->where('month', '<', $monthNow);
                      });
                })
                ->where('status', 'ACTIVE');

            $ticketsToUpdateCount = $ticketsQuery->count();

            if ($ticketsToUpdateCount === 0) {
                $this->line(" -> Active lottery tickets before $monthNow/$yearNow: 0. Skipping.");
                $this->line("--------------------------------------------------");
                continue;
            }

            $this->line(" -> Active lottery tickets found to update: $ticketsToUpdateCount");

            // 4. Update the tickets
            DB::transaction(function () use ($ticketsQuery, $statusToSet, $dryRun, $ticketsToUpdateCount, $monthNow, $yearNow) {
                if ($dryRun) {
                    $this->warn(" -> [DRY RUN] Would update $ticketsToUpdateCount active tickets before $monthNow/$yearNow to '$statusToSet'.");
                } else {
                    $updatedCount = $ticketsQuery->update([
                        'status' => $statusToSet,
                        'updated_at' => now(),
                    ]);
                    $this->info(" -> [LIVE] Successfully updated $updatedCount active tickets before $monthNow/$yearNow to '$statusToSet'!");
                }
            });

            $this->line("--------------------------------------------------");
        }

        $this->info("✓ Execution finished successfully.");
        return Command::SUCCESS;
    }
}
