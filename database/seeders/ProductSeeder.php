<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $file = fopen(database_path('seeders/products.csv'), 'r');
        $header = fgetcsv($file, null, "|");

        while (($row = fgetcsv($file, null, "|")) !== false) {
            \App\Models\Product::updateOrCreate(
                ['id' => $row[0]],
                [
                    'sk_produk' => $row[1] ?? null,
                    'kode_group_produk' => $row[2] ?? null,
                    'group_produk' => $row[3] ?? null,
                    'kode_produk' => $row[4] ?? null,
                    'nama_produk' => $row[5] ?? null,
                    'nama_singkat_produk' => $row[6] ?? null,
                    'kode_sub_produk' => $row[7] ?? null,
                    'nama_sub_produk' => $row[8] ?? null,
                    'gol_mas' => $row[9] ?? null,
                    'date_time' => $row[10] ? new \DateTime($row[10]) : null,
                    'batch_date' => $row[11] ? new \DateTime($row[11]) : null,
                    'insert_date' => $row[12] ? new \DateTime($row[12]) : null,
                ]
            );
        }

        fclose($file);
    }
}
