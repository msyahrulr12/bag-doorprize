<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')->insert([
            [
                'group' => 'general',
                'key' => 'base_comparison_year',
                'value' => '2026',
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'general',
                'key' => 'base_comparison_month',
                'value' => '1',
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['base_comparison_year', 'base_comparison_month'])->delete();
    }
};
