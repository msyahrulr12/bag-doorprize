<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly Data Ingestion (Starts early to feed subsequent reports)
Schedule::command('app:process-point-history-command')->dailyAt('00:01');

// Winner Export (Handles events that ended yesterday)
Schedule::command('app:export-winners')->dailyAt('01:00');

// Coupon Reporting
Schedule::command('app:report-coupon-command')->dailyAt('01:30');

// Master Data Sync (Optional, but kept active to ensure branch mappings are current)
Schedule::command('app:import-region-cabang-command')->dailyAt('02:00');

// PDF Generation (Heavy task, scheduled later to ensure all point ingestion is complete)
Schedule::command('app:generate-bank-statement-pdf-command')->dailyAt('03:00');

