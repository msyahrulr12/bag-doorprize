<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Winner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ExportWinnersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:export-winners {--date= : The date the event ended (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export winners of events that ended on a specific date to CSV';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->option('date') ?: Carbon::yesterday()->toDateString();
        $this->info("Checking for events that ended on: {$date}");

        $events = Event::whereDate('event_ended_at', $date)->get();

        if ($events->isEmpty()) {
            $this->info("No events found that ended on {$date}.");
            return;
        }

        foreach ($events as $event) {
            $this->exportWinnersForEvent($event);
        }
    }

    protected function exportWinnersForEvent(Event $event)
    {
        $winners = Winner::where('event_code', $event->event_code)->get();

        if ($winners->isEmpty()) {
            $this->warn("No winners found for event: {$event->event_name} ({$event->event_code})");
            return;
        }

        $fileName = "winners_{$event->event_code}_" . now()->format('Ymd_His') . ".csv";
        $filePath = "exports/winners/{$fileName}";

        $header = [
            'ID',
            'CIF',
            'Account Number',
            'Name',
            'Email',
            'Phone',
            'Prize Name',
            'Prize Tier',
            'Winning Number',
            'Drawn At',
            'Status'
        ];

        $callback = function () use ($winners, $header) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $header);

            foreach ($winners as $winner) {
                fputcsv($file, [
                    $winner->id,
                    $winner->participant_cif,
                    $winner->participant_account_number,
                    $winner->participant_name,
                    $winner->participant_email,
                    $winner->participant_phone_number,
                    $winner->prize_name,
                    $winner->prize_tier,
                    $winner->winning_number,
                    $winner->drawn_at,
                    $winner->status
                ]);
            }

            fclose($file);
        };

        // Capture output to save to storage
        ob_start();
        $callback();
        $csvContent = ob_get_clean();

        Storage::disk('public')->put($filePath, $csvContent);

        $this->info("Exported winners for event '{$event->event_name}' to {$filePath}");
    }
}
