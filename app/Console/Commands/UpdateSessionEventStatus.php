<?php

namespace App\Console\Commands;

use App\Models\DrawSession;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class UpdateSessionEventStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-session-event-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates Draw Session and Event statuses based on their end times';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        // Update Events
        $this->info("Checking events for completion...");
        $eventsToComplete = Event::where('status', Event::STATUS_ACTIVE)
            ->where('event_ended_at', '<=', $now)
            ->get();

        foreach ($eventsToComplete as $event) {
            $event->update(['status' => Event::STATUS_COMPLETED]);
            $this->info("Event [{$event->event_name}] (ID: {$event->id}) has been set to COMPLETED.");
        }

        // Update Draw Sessions
        $this->info("Checking draw sessions for completion...");
        $sessionsToDeactivate = DrawSession::where('status', DrawSession::STATUS_ACTIVE)
            ->where('ended_at', '<=', $now)
            ->get();

        foreach ($sessionsToDeactivate as $session) {
            $session->update(['status' => DrawSession::STATUS_INACTIVE]);
            $this->info("Draw Session [{$session->name}] (ID: {$session->id}) has been set to INACTIVE.");
        }

        $this->info("Finished updating statuses.");
    }
}
