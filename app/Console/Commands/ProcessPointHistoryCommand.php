<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPointHistory;
use App\Models\Branch;
use App\Models\Event;
use App\Models\Product;
use App\Models\Setting;
use DB;
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

            $eventId = $event?->id;
            $subMonth = $settings['point_sub_month'] ?? 1;
            $currentDate = now()->subMonths($subMonth);
            $year = $currentDate->year;
            $month = $currentDate->month;
            $startOfMonth = $currentDate->startOfMonth()->format('Y-m-d');

            // Init DB Core T24
            $dbT24 = DB::connection('db_core_t24');

            // Process NTB
            $totalNtb = $dbT24->table('undian_ntb')->where('file_date', $startOfMonth)->count();
            $this->info("Found {$totalNtb} NTB records to process.");
            $barNtb = $this->output->createProgressBar($totalNtb);
            $barNtb->start();

            $dbT24
                ->table('undian_ntb')
                ->where('file_date', $startOfMonth)
                ->orderBy('cif', 'asc')
                ->chunk(500, function ($customers) use ($products, $branches, $month, $year, $settings, $eventId, $barNtb) {
                    ProcessPointHistory::dispatch(
                        $customers->toArray(),
                        $products,
                        $branches,
                        $month,
                        $year,
                        'ntb',
                        $settings,
                        $eventId
                    )->onQueue('imports');
                    $barNtb->advance($customers->count());
                });
            $barNtb->finish();
            $this->newLine(2);

            // Process ETB
            $totalEtb = $dbT24->table('undian_etb')->where('file_date', $startOfMonth)->count();
            $this->info("Found {$totalEtb} ETB records to process.");
            $barEtb = $this->output->createProgressBar($totalEtb);
            $barEtb->start();

            $dbT24
                ->table('undian_etb')
                ->where('file_date', $startOfMonth)
                ->orderBy('cif', 'asc')
                ->chunk(500, function ($customers) use ($products, $branches, $month, $year, $settings, $eventId, $barEtb) {
                    ProcessPointHistory::dispatch(
                        $customers->toArray(),
                        $products,
                        $branches,
                        $month,
                        $year,
                        'etb',
                        $settings,
                        $eventId
                    )->onQueue('imports');
                    $barEtb->advance($customers->count());
                });
            $barEtb->finish();
            $this->newLine(2);

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

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($chunks as $chunk) {
            ProcessPointHistory::dispatch(
                $chunk,
                $products,
                $branches,
                $month,
                $year,
                $type,
                $settings,
                $eventId
            )->onQueue('imports');
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->newLine(2);
    }
}
