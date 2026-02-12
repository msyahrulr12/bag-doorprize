<?php

namespace App\Console\Commands;

use App\Jobs\ReportCouponJob;
use App\Models\Setting;
use Illuminate\Console\Command;

class ReportCouponCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:report-coupon-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and create report in CSV for update coupon on big data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pointSubMonth = Setting::where('key', 'point_sub_month')->first()->value;
        $date = now()->subMonths($pointSubMonth);
        ReportCouponJob::dispatch($date->month, $date->year);

        return 0;
    }
}
