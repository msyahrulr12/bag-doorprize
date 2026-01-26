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
        Schema::table('point_histories', function (Blueprint $table) {
            // Add unique constraint to prevent duplicate entries
            // A point history should be unique per account, month, and year
            $table->unique(['account_id', 'month', 'year'], 'unique_point_history_per_account_month_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('point_histories', function (Blueprint $table) {
            $table->dropUnique('unique_point_history_per_account_month_year');
        });
    }
};
