<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;

class ExportPlainDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:export-plain-data-command {--output-path= : Path file akan di-export } {--date= : Tanggal export data undian yang akan dieksport} {--include-points= : Apakah ingin melakukan export file dengan file point history undian}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $outputPath = $this->option('output-path');
        $date = $this->option('date');
        $includePoints = (bool) $this->option('include-points');

        if (empty($outputPath)) {
            $this->error('Output path is required.');
            return 1;
        }

        if (empty($date)) {
            $this->error('Date is required.');
            return 1;
        }

        // Get data from big data
        $dbT24 = DB::connection('db_core_t24');

        // NTB
        $tableNtb = env('DB_CORE_T24_TABLE_NTB', 'undian_ntb');
        $totalNtb = $dbT24->table($tableNtb)->where('file_date', $endOfMonth)->count();

        // ETB
        $tableEtb = env('DB_CORE_T24_TABLE_ETB', 'undian_etb');
        $totalEtb = $dbT24->table($tableEtb)->where('file_date', $endOfMonth)->count();
    }
}
