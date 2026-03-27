<?php

namespace App\Console\Commands;

use App\Helper\PdfHelper;
use Illuminate\Console\Command;

class TestTermConditionPdfCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-term-condition-pdf';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a test PDF for term-conditions to check page breaks and layout';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating Test PDF for Term Conditions...');

        $path = storage_path('app/public/test');
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        $filename = 'test_term_conditions_' . now()->format('Ymd_His') . '.pdf';

        try {
            // Data needed by the view (if any)
            $data = [];

            PdfHelper::writeAndSave('pdf.term-conditions', $data, $path, $filename);

            $this->info("PDF generated successfully!");
            $this->info("Location: " . $path . '/' . $filename);

            // Helpful for local testing if the user has a way to open it
            $this->warn("You can check this file in the storage/app/public/test directory.");
        } catch (\Exception $e) {
            $this->error('Failed to generate PDF: ' . $e->getMessage());
        }
    }
}
