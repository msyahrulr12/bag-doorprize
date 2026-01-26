<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Default settings
        Setting::updateOrCreate(
            ['key' => 'point_divider'],
            [
                'group' => 'general',
                'value' => 100000,
                'type' => 'integer',
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'min_opening_balance'],
            [
                'group' => 'general',
                'value' => 500000,
                'type' => 'integer',
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'base_point_ntb'],
            [
                'group' => 'general',
                'value' => 50,
                'type' => 'integer',
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'region_weights'],
            [
                'group' => 'drawing',
                'value' => json_encode([
                    'Jawa' => 50,
                    'Sumatera' => 20,
                    'Sulawesi' => 20,
                    'Lainnya' => 10,
                ]),
                'type' => 'json',
            ]
        );
    }
}
