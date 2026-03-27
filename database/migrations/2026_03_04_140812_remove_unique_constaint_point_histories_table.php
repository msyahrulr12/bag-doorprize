<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Drop existing unique constraints that conflict with many-times correction
        Schema::table('point_histories', function (Blueprint $table) {
            try {
                $table->dropUnique('unique_ph_full_key');
            } catch (\Exception $e) {
            }
            try {
                // $table->dropUnique('unique_point_history_per_account_month_year');
            } catch (\Exception $e) {
            }
        });

        Schema::table('lottery_tickets', function (Blueprint $table) {
            try {
                // $table->dropUnique('unique_lt_full_key');
            } catch (\Exception $e) {
            }
            try {
                // $table->dropUnique('unique_lt_acc_mo_yr');
            } catch (\Exception $e) {
            }
        });

        // 2. Add stable unique_key for UPSERT functionality (allows many nulls for MANUAL entries)
        if (!Schema::hasColumn('point_histories', 'unique_key')) {
            Schema::table('point_histories', function (Blueprint $table) {
                $table->string('unique_key')->nullable();
            });
        }

        if (!Schema::hasColumn('lottery_tickets', 'unique_key')) {
            Schema::table('lottery_tickets', function (Blueprint $table) {
                $table->string('unique_key')->nullable();
            });
        }

        // 3. Populate unique_key for existing SYSTEM records 
        DB::statement("UPDATE point_histories SET unique_key = 'ph_sys_' || account_id || '_' || month || '_' || year || '_' || type WHERE source = 'SYSTEM' AND unique_key IS NULL");
        DB::statement("UPDATE lottery_tickets SET unique_key = 'lt_sys_' || event_id || '_' || participant_id || '_' || month || '_' || year WHERE source = 'SYSTEM' AND unique_key IS NULL");

        // 4. Add the unique indices
        Schema::table('point_histories', function (Blueprint $table) {
            $table->unique('unique_key');
        });

        Schema::table('lottery_tickets', function (Blueprint $table) {
            $table->unique('unique_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('point_histories', function (Blueprint $table) {
            $table->dropUnique(['unique_key']);
            $table->dropColumn('unique_key');
        });

        Schema::table('lottery_tickets', function (Blueprint $table) {
            $table->dropUnique(['unique_key']);
            $table->dropColumn('unique_key');
        });
    }
};
