<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly Data Ingestion (Starts early to feed subsequent reports)
Schedule::command('app:process-point-history-command')->cron(env('SCHEDULER_POINT_HISTORY_COMMAND', '0 8 2 * *'));

// Winner Export (Handles events that ended yesterday)
Schedule::command('app:export-winners')->dailyAt('01:00');

// Coupon Reporting
// Schedule::command('app:report-coupon-command')->dailyAt('01:30');

// Master Data Sync (Optional, but kept active to ensure branch mappings are current)
// Schedule::command('app:import-region-cabang-command')->dailyAt('02:00');

// PDF Generation (Heavy task, scheduled later to ensure all point ingestion is complete)
Schedule::command('app:generate-bank-statement-pdf-command')->cron(env('SCHEDULER_GENERATE_BANK_STATEMENT', '0 10 4 * *'));

// Status Maintenance
Schedule::command('app:update-session-event-status')->hourly();

// Ticket Status Maintenance (Runs every month on the 4th at 4:00 AM)
Schedule::command('app:update-tickets-status')->cron(env('SCHEDULER_UPDATE_TICKETS_STATUS', '0 4 4 * *'));

