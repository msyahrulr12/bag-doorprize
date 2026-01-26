<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Searching branches.csv from seeders folder...');

        $csvFile = database_path('seeders/branches.csv');

        if (!file_exists($csvFile)) {
            $this->command->error('CSV file not found: ' . $csvFile);
            return;
        }

        $this->command->info('File branches.csv found. Opening file...');

        $file = fopen($csvFile, 'r');

        // Skip the header row
        $header = fgetcsv($file, 0, '|');

        // Read and process each row
        $this->command->info('Processing each row of data...');
        while (($row = fgetcsv($file, 0, '|')) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Map CSV columns to database fields
            // Adjust the mapping based on your actual database table structure
            \App\Models\Branch::createOrFirst([
                'branch_code' => $row[0] ?? null,
                'sk_branch' => $row[0] ?? null,
                'sandi_pelapor_kantor_lbu' => $row[1] ?? null,
                'nama_sandi_pelapor' => $row[2] ?? null,
                'company_book' => $row[3] ?? null,
                'company_name' => $row[4] ?? null,
                'branch_name' => $row[4] ?? null,
                'name_address' => $row[5] ?? null,
                'address' => $row[5] ?? null,
                'date_from' => $row[6] ? new \DateTime($row[6]) : null,
                'date_to' => $row[7] ? new \DateTime($row[7]) : null,
                'version' => $row[8] ?? null,
                'wib' => $row[9] ?? null,
                'update_date' => $row[10] ? new \DateTime($row[10]) : null,
                'update_regional1' => $row[11] ?? null,
                'update_date1' => $row[12] ? new \DateTime($row[12]) : null,
                'new_regional_head' => $row[13] ?? null,
            ]);
        }

        fclose($file);

        $this->command->info('Branches seeded successfully!');
    }
}
