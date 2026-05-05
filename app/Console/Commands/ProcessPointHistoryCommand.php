<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPointHistory;
use App\Models\Branch;
use App\Models\Event;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;

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
        $lock = Cache::lock('process-point-history-import', 3600); // 1 hour lock

        if (!$lock->get()) {
            if ($this->option('force')) {
                $this->warn('Another import is running. Forcing execution...');
                Cache::forget('process-point-history-import');
                $lock = Cache::lock('process-point-history-import', 3600);
                $lock->get();
            } else {
                $this->error('Another import process is already running!');
                $this->info('Use --force option to override this lock.');
                return 1;
            }
        }

        try {
            $this->info('Starting database backup before process...');
            $result = $this->call('app:database-backup');
            
            if ($result !== 0) {
                if (!$this->confirm('Database backup failed. Do you want to continue without backup?', false)) {
                    $this->error('Process aborted due to backup failure.');
                    return 1;
                }
            }

            $this->info('Starting import process...');
            $this->newLine();

            // Pre-load products, branches once.
            // Keys are trimmed to guard against leading/trailing whitespace in the DB values
            // that would cause array lookups to miss (e.g. 'ID0019999 ' vs 'ID0019999').
            $products = collect(Product::pluck('id', 'kode_produk'))->mapWithKeys(fn($id, $key) => [trim($key) => $id])->toArray();
            $branches = collect(Branch::pluck('id', 'company_book'))->mapWithKeys(fn($id, $key) => [trim($key) => $id])->toArray();

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
            $endOfMonth = $currentDate->endOfMonth()->format('Y-m-d');

            // Init DB Core T24
            $dbT24 = DB::connection('db_core_t24');
            $tableNtb = env('DB_CORE_T24_TABLE_NTB', 'undian_ntb');

            // Process NTB
            $totalNtb = $dbT24->table($tableNtb)->where('file_date', $endOfMonth)->count();
            $this->info("Found {$totalNtb} NTB records to process.");
            $barNtb = $this->output->createProgressBar($totalNtb);
            $barNtb->start();

            $dbT24
                ->table($tableNtb)
                ->where('file_date', $endOfMonth)
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
            $tableEtb = env('DB_CORE_T24_TABLE_ETB', 'undian_etb');
            $totalEtb = $dbT24->table($tableEtb)->where('file_date', $endOfMonth)->count();
            $this->info("Found {$totalEtb} ETB records to process.");
            $barEtb = $this->output->createProgressBar($totalEtb);
            $barEtb->start();

            $dbT24
                ->table($tableEtb)
                ->where('file_date', $endOfMonth)
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
            Log::error('Import failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return 1;
        } finally {
            $lock->release();
        }
    }
}
