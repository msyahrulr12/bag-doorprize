<?php

namespace App\Console\Commands;

use App\Models\Branch;
use Illuminate\Console\Command;

class ImportRegionCabangCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-region-cabang-command';

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
        $csvFile = public_path('imports/mapping_wilayah_cabang.csv');
        $file = fopen($csvFile, 'r');

        // Skip the header row
        $header = fgetcsv($file, 0, ',');

        $branches = Branch::all();

        // Read and process each row
        $this->info('Processing each row of data...');
        while (($row = fgetcsv($file, 0, ',')) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            $row = collect($row)->map(function ($r) {
                return trim($r);
            })->toArray();

            $region = isset($row[3]) ? $row[3] : null;

            if (!$region) {
                continue;
            }

            // Find branch with last 3 digit is $row[0]
            $branches = $branches->map(function ($branch) use ($row, $region) {
                if (substr($branch->company_book, -3) == $row[0]) {
                    $branch->update([
                        'region' => ucfirst(strtolower($region)),
                    ]);
                }

                return $branch;
            });
        }

        fclose($file);
    }
}
