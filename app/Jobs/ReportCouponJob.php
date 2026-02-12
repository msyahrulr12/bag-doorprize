<?php

namespace App\Jobs;

use App\Services\ReportPointService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReportCouponJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $year,
        private int $month
    ) {
        $this->onQueue('reports');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $reportPointService = new ReportPointService();
        $reportPointService->reportPoint($this->month, $this->year);
    }
}
