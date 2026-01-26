<?php

namespace App\Console\Commands;

use App\Helper\DateHelper;
use App\Jobs\ProcessPointHistory;
use App\Models\Branch;
use App\Models\Event;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Console\Command;
use Storage;

class ProcessPointHistoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-point-history-command {--force : Force run even if already running}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import file ntb and etb from minio and processing file.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Prevent concurrent execution using cache lock
        $lock = \Cache::lock('process-point-history-import', 3600); // 1 hour lock

        if (!$lock->get()) {
            if ($this->option('force')) {
                $this->warn('Another import is running. Forcing execution...');
                \Cache::forget('process-point-history-import');
                $lock = \Cache::lock('process-point-history-import', 3600);
                $lock->get();
            } else {
                $this->error('Another import process is already running!');
                $this->info('Use --force option to override this lock.');
                return 1;
            }
        }

        try {
            $this->info('Starting import process...');
            $this->newLine();

            // Pre-load products, branches once
            $products = Product::pluck('id', 'kode_produk')->toArray();
            $branches = Branch::pluck('id', 'company_book')->toArray();

            // Get active event
            $event = Event::where('status', Event::STATUS_ACTIVE)->first();
            if (!$event) {
                $this->error('No active event found!');
                return 1;
            }

            // Get current setting
            $settings = Setting::where('group', 'general')->pluck('value', 'key')->toArray();

            // Files to process
            $subMonth = $settings['point_sub_month'] ?? 1;
            $lastMonth = DateHelper::getMonthYear(now()->subMonths($subMonth));
            $files = [
                'ntb' => "NTB {$lastMonth}.csv",
                'etb' => "ETB {$lastMonth}.csv",
            ];

            $disk = Storage::disk('s3');

            foreach ($files as $type => $filename) {
                if (!$disk->exists($filename)) {
                    $this->warn("File {$filename} not found in S3. Skipping...");
                    continue;
                }

                $content = $disk->get($filename);

                // Extract month and year from filename (format: TYPE Month Year.csv)
                preg_match('/([a-zA-Z]+)\s+(\d{4})/', $filename, $matches);
                $monthName = $matches[1] ?? '';
                $year = (int) ($matches[2] ?? now()->year);

                // Use simple map for Indonesian/English months just in case
                $monthsMap = [
                    'Januari' => 1,
                    'Februari' => 2,
                    'Maret' => 3,
                    'April' => 4,
                    'Mei' => 5,
                    'Juni' => 6,
                    'Juli' => 7,
                    'Agustus' => 8,
                    'September' => 9,
                    'Oktober' => 10,
                    'November' => 11,
                    'Desember' => 12,
                ];
                $month = $monthsMap[$monthName] ?? (\Carbon\Carbon::hasFormat($monthName, 'F') ? \Carbon\Carbon::parse($monthName)->month : now()->month);
                $separator = '|';

                $this->processFile($content, $type, $month, $year, $separator, $products, $branches, $event->id, $settings);
            }

            $this->newLine();
            $this->info('✓ All import jobs have been dispatched!');

            return 0;
        } catch (\Exception $e) {
            $this->error('Import failed: ' . $e->getMessage());
            \Log::error('Import failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return 1;
        } finally {
            $lock->release();
        }
    }

    private function processFile(string $content, string $type, int $month, int $year, string $separator, array $products, array $branches, int $eventId, array $settings): void
    {
        $this->info("Processing " . strtoupper($type) . " file for {$month}/{$year}...");

        // Read CSV file
        $content = trim($content);
        $lines = explode(PHP_EOL, $content);
        $rows = array_map(fn($line) => str_getcsv($line, $separator), $lines);
        $header = array_shift($rows);

        if (empty($header) || empty($rows)) {
            $this->warn("File for " . strtoupper($type) . " is empty or has no header.");
            return;
        }

        // Setup chunks
        $chunkSize = 1000;
        $chunks = array_chunk($rows, $chunkSize);

        $this->info('Dispatching ' . count($chunks) . ' job(s) to queue...');

        $bar = $this->output->createProgressBar(count($chunks));
        $bar->start();

        foreach ($chunks as $chunk) {
            ProcessPointHistory::dispatch(
                $chunk,
                $header,
                $products,
                $branches,
                $month,
                $year,
                $type,
                $settings,
                $eventId
            )->onQueue('imports');
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }
}
