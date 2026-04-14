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
                'value' => 10,
                'type' => 'integer',
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'point_sub_month'],
            [
                'group' => 'general',
                'value' => 1,
                'type' => 'integer',
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'threshold_reduction_balance'],
            [
                'group' => 'general',
                'value' => 100000,
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

        Setting::updateOrCreate(
            ['key' => 'activate_re_draw_and_confirm'],
            [
                'group' => 'drawing',
                'value' => true,
                'type' => 'boolean',
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'merge_pdf_bank_statement'],
            [
                'group' => 'general',
                'value' => false,
                'type' => 'boolean',
            ]
        );

        Setting::updateOrCreate(
            ['key' => 'draw_delay'],
            [
                'group' => 'drawing',
                'value' => 8,
                'type' => 'integer',
            ]
        );
    }
}
