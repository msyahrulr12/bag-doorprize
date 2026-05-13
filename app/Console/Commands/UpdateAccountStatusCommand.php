<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Setting;
use DB;
use Illuminate\Console\Command;

class UpdateAccountStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-account-status 
                            {--date= : The file_date to filter T24 data (YYYY-MM-DD)}
                            {--file= : The CSV file path to process manually}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync account status (exclude_flag, inactiv_marker, confi_flag) from T24 to accounts table.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $file = $this->option('file');

        if ($file) {
            return $this->processCsv($file);
        }

        $this->info('Starting status sync process from T24...');

        $date = $this->option('date');
        if (empty($date)) {
            $settings = Setting::where('group', 'general')->pluck('value', 'key')->toArray();
            $subMonth = (int) ($settings['point_sub_month'] ?? 1);
            $currentDate = now()->subMonths($subMonth);
            $date = $currentDate->endOfMonth()->format('Y-m-d');
        }

        $this->info("Filtering data for file_date: {$date}");

        $dbT24 = DB::connection('db_core_t24');
        $tableNtb = env('DB_CORE_T24_TABLE_NTB', 'undian_ntb');
        $tableEtb = env('DB_CORE_T24_TABLE_ETB', 'undian_etb');

        $this->processTable($dbT24, $tableNtb, $date, 'NTB');
        $this->processTable($dbT24, $tableEtb, $date, 'ETB');

        $this->info('✓ All account statuses updated!');

        return 0;
    }

    private function processCsv($filePath)
    {
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Processing CSV file: {$filePath}");

        $file = fopen($filePath, 'r');
        $header = fgetcsv($file);
        
        // Normalize headers
        $header = array_map(function($h) {
            return strtolower(trim($h));
        }, $header);

        $rowCount = 0;
        $batchSize = 1000;
        $records = [];

        while (($row = fgetcsv($file)) !== false) {
            $records[] = array_combine($header, $row);
            $rowCount++;

            if (count($records) >= $batchSize) {
                $this->updateBatch($records);
                $records = [];
                $this->info("Processed {$rowCount} rows...");
            }
        }

        if (!empty($records)) {
            $this->updateBatch($records);
        }

        fclose($file);
        $this->info("✓ Successfully processed {$rowCount} rows from CSV.");
        return 0;
    }

    private function updateBatch(array $rows)
    {
        $groupedByStatus = [
            Account::STATUS_CONFI => [],
            Account::STATUS_INACTIVE => [],
            Account::STATUS_EXCLUDE => [],
            Account::STATUS_ACTIVE => [],
        ];

        foreach ($rows as $row) {
            $accNo = $row['account_number'] ?? ($row['ac_id'] ?? ($row['account_no'] ?? null));
            if (!$accNo) continue;

            $inactivMarker = $row['inactiv_marker'] ?? ($row['inactivmarker'] ?? null);
            $excludeFlag = $row['exclude_flag'] ?? ($row['excludeflag'] ?? null);
            $confiFlag = $row['confi_flag'] ?? ($row['confiflag'] ?? null);

            $status = null;
            if ($confiFlag !== null && $confiFlag !== '' && $confiFlag !== 'N' && $confiFlag !== '0') {
                $status = Account::STATUS_CONFI;
            } elseif ($inactivMarker !== null && $inactivMarker !== '' && $inactivMarker !== 'N' && $inactivMarker !== '0') {
                $status = Account::STATUS_INACTIVE;
            } elseif ($excludeFlag !== null && $excludeFlag !== '' && $excludeFlag !== 'N' && $excludeFlag !== '0') {
                $status = Account::STATUS_EXCLUDE;
            } else {
                $status = Account::STATUS_ACTIVE;
            }

            $groupedByStatus[$status][] = $accNo;
        }

        foreach ($groupedByStatus as $status => $accNos) {
            if (!empty($accNos)) {
                Account::whereIn('account_number', $accNos)->update([
                    'status' => $status,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function processTable($dbT24, $table, $date, $label)
    {
        $total = $dbT24->table($table)->where('file_date', $date)->count();
        if ($total === 0) {
            $this->warn("No records found in {$table} for {$date}. Skipping.");
            return;
        }

        $this->info("Processing {$total} records from {$label} ({$table})...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $dbT24->table($table)->where('file_date', $date)->orderBy('cif', 'asc')->chunk(1000, function ($customers) use ($bar) {
            $groupedByStatus = [
                Account::STATUS_CONFI => [],
                Account::STATUS_INACTIVE => [],
                Account::STATUS_EXCLUDE => [],
                Account::STATUS_ACTIVE => [],
            ];

        foreach ($customers as $customer) {
                $customerArr = (array) $customer;
                $accNo = $customerArr['account_number'] ?? ($customerArr['ac_id'] ?? null);
                if (!$accNo) continue;

                $inactivMarker = $customerArr['inactiv_marker'] ?? ($customerArr['inactivMarker'] ?? null);
                $excludeFlag = $customerArr['exclude_flag'] ?? ($customerArr['excludeFlag'] ?? null);
                $confiFlag = $customerArr['confi_flag'] ?? ($customerArr['confiFlag'] ?? null);

                $status = null;
                if ($confiFlag !== null && $confiFlag !== '' && $confiFlag !== 'N') {
                    $status = Account::STATUS_CONFI;
                } elseif ($inactivMarker !== null && $inactivMarker !== '' && $inactivMarker !== 'N') {
                    $status = Account::STATUS_INACTIVE;
                } elseif ($excludeFlag !== null && $excludeFlag !== '' && $excludeFlag !== 'N') {
                    $status = Account::STATUS_EXCLUDE;
                } else {
                    $status = Account::STATUS_ACTIVE;
                }

                $groupedByStatus[$status][] = $accNo;
                $bar->advance();
            }

            foreach ($groupedByStatus as $status => $accNos) {
                if (!empty($accNos)) {
                    Account::whereIn('account_number', $accNos)->update([
                        'status' => $status,
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        $bar->finish();
        $this->newLine();
    }
}