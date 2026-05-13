<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PointHistory;
use App\Models\LotteryTicket;
use App\Models\Participant;
use App\Models\Setting;

class FixAprilPointHistoryBugsCommand extends Command
{
    protected $signature = 'app:fix-april-point-history';
    protected $description = 'Secretly fix the wrongly expired tickets caused by the admin fee threshold bug.';

    public function handle()
    {
        $this->info('Starting secret database fix for April threshold bug...');

        $thresholdSetting = Setting::where('key', 'threshold_reduction_balance')->first();
        $threshold = $thresholdSetting ? (float) $thresholdSetting->value : 100000;

        // Find all point histories in 2026 where points were EXPIRED
        $wrongHistories = PointHistory::where('year', 2026)
            ->where('month', 4) // April
            ->where('type', PointHistory::POINT_TYPE_EXPIRED)
            ->get();

        $this->info("Restoring accounts wrongly expired by admin fees...");
        $fixedExpiredCount = 0;
        $restoredTickets = 0;

        foreach ($wrongHistories as $ph) {
            $prevMonth = PointHistory::where('account_id', $ph->account_id)->where('year', 2026)->where('month', 3)->first();
            $prevAmt = $prevMonth ? $prevMonth->amount : 0;
            $currAmt = $ph->amount;
            $growth = $currAmt - $prevAmt;

            if ($growth < 0 && abs($growth) <= $threshold) {
                $participant = Participant::where('account_id', $ph->account_id)->first();
                $accNo = $participant ? $participant->participant_account_number : 'UNKNOWN';

                $ph->update([
                    'type' => PointHistory::POINT_TYPE_EARN,
                    'points' => 0,
                    'description' => "REK {$accNo} BERTAMBAH 0 KUPON",
                ]);

                if ($participant) {
                    $updated = LotteryTicket::where('participant_id', $participant->id)
                        ->where('status', LotteryTicket::STATUS_RESET)
                        ->update(['status' => LotteryTicket::STATUS_ACTIVE]);
                    $restoredTickets += $updated;
                }
                $fixedExpiredCount++;
            }
        }

        $this->info("Fix completed secretly.");
        $this->info("Restored $fixedExpiredCount accounts from admin fee bug.");
        $this->info("Restored $restoredTickets lottery tickets to ACTIVE status.");
    }
}
