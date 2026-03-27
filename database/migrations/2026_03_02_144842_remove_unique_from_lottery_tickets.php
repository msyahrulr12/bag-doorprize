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
        Schema::table('lottery_tickets', function (Blueprint $table) {
            $table->dropUnique('lottery_tickets_participant_month_year_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lottery_tickets', function (Blueprint $table) {
            $table->unique(['participant_id', 'month', 'year'], 'lottery_tickets_participant_month_year_unique');
        });
    }
};
