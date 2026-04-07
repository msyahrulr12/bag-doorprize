<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\LotteryTicket;
use App\Models\PointHistory;
use App\Models\Winner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RollbackPointHistoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rollback-point-history-command {--month= : The month to rollback (1-12)} {--year= : The year to rollback (e.g. 2026)} {--event-id= : The event ID to reset ticket numbers for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback Point History, Lottery Tickets, and reset event last_ticket_number for a specific month';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $month = (int) $this->option('month');
        $year = (int) $this->option('year');
        $eventId = (int) $this->option('event-id');

        if (!$month || !$year || !$eventId) {
            $this->error('Month, year, and event-id are required options.');
            return 1;
        }

        $event = Event::find($eventId);
        if (!$event) {
            $this->error("Event with ID {$eventId} not found.");
            return 1;
        }

        // Safety check: Check if winners exist for this month/year/event
        $winnersCount = Winner::whereHas('lotteryTicket', function ($query) use ($month, $year, $eventId) {
            $query->where('month', $month)->where('year', $year)->where('event_id', $eventId);
        })->count();

        if ($winnersCount > 0) {
            if (!$this->confirm("Found {$winnersCount} winners for the selected month/event. Rolling back might cause data inconsistency. Proceed anyway?")) {
                $this->info('Rollback canceled.');
                return 0;
            }
        }

        $this->info("Starting rollback for {$month}/{$year}, Event: {$event->event_name}...");

        DB::beginTransaction();
        try {
            // 1. Delete Anomaly Records
            $anomaliesDeleted = DB::table('point_history_anomalies')
                ->where('month', $month)
                ->where('year', $year)
                ->delete();
            $this->info("- Deleted {$anomaliesDeleted} anomaly records.");

            // 2. Revert statuses of tickets from PREVIOUS months that were marked as RESET during target month
            // We identify them by updated_at being in the same month/year or just by current RESET status
            // Actually, the previous logic used updated_at::date. Since we might be running this later,
            // we should be careful. 
            // Better: Any ticket that was RESET but belongs to a previous month.
            $ticketsReverted = LotteryTicket::where('status', 'RESET')
                ->where(function ($query) use ($month, $year) {
                    $query->where('year', '<', $year)
                        ->orWhere(function ($q) use ($month, $year) {
                            $q->where('year', $year)->where('month', '<', $month);
                        });
                })
                ->where('event_id', $eventId)
                ->update(['status' => 'ACTIVE', 'updated_at' => now()]);
            $this->info("- Reverted {$ticketsReverted} tickets from RESET back to ACTIVE.");

            // 3. Delete Point History records (SYSTEM only)
            // Note: PointHistory is not bound to event_id directly, but we filter by month/year
            $phDeleted = PointHistory::where('month', $month)
                ->where('year', $year)
                ->where('source', 'SYSTEM')
                ->delete();
            $this->info("- Deleted {$phDeleted} point history records.");

            // 4. Delete Lottery Tickets
            $ticketsDeleted = LotteryTicket::where('month', $month)
                ->where('year', $year)
                ->where('event_id', $eventId)
                ->delete();
            $this->info("- Deleted {$ticketsDeleted} lottery tickets.");

            // Reset Event.last_ticket_number
            // Find the highest range_end string from remaining tickets for this event
            $maxTicketString = LotteryTicket::where('event_id', $eventId)
                ->where(function ($query) use ($month, $year) {
                    $query->where('year', '<', $year)
                        ->orWhere(function ($q) use ($month, $year) {
                            $q->where('year', $year)->where('month', '<', $month);
                        });
                })
                ->max('range_end');

            if ($maxTicketString) {
                // Parse the string back to integer and add 1 for the next starting number
                $newLastTicket = \App\Utils\TicketHelper::parse($maxTicketString) + 1;
            } else {
                $newLastTicket = 0;
            }

            $event->update(['last_ticket_number' => $newLastTicket]);
            $this->info("- Reset Event [{$event->event_name}] last_ticket_number to: {$newLastTicket}.");

            DB::commit();
            $this->info("Rollback successful.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Rollback failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
