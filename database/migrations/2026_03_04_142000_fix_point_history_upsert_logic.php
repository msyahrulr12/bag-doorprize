<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Drop existing unique constraints if they still exist
        Schema::table('point_histories', function (Blueprint $table) {
            $indices = DB::select("SELECT indexname FROM pg_indexes WHERE indexname = 'unique_ph_full_key'");
            if (!empty($indices)) {
                // $table->dropUnique('unique_ph_full_key');
            }
        });

        Schema::table('lottery_tickets', function (Blueprint $table) {
            $indices = DB::select("SELECT indexname FROM pg_indexes WHERE indexname = 'unique_lt_full_key'");
            if (!empty($indices)) {
                // $table->dropUnique('unique_lt_full_key');
            }
        });

        // 2. Clean up existing SYSTEM duplicates to prevent migration failure
        DB::statement("DELETE FROM point_histories h1 USING point_histories h2 WHERE h1.id < h2.id AND h1.account_id = h2.account_id AND h1.month = h2.month AND h1.year = h2.year AND h1.type = h2.type AND h1.source = 'SYSTEM'");
        DB::statement("DELETE FROM lottery_tickets l1 USING lottery_tickets l2 WHERE l1.id < l2.id AND l1.event_id = l2.event_id AND l1.participant_id = l2.participant_id AND l1.month = l2.month AND l1.year = l2.year AND l1.source = 'SYSTEM'");

        // 3. Add column (without unique constraint for now)
        if (!Schema::hasColumn('point_histories', 'unique_key')) {
            Schema::table('point_histories', function (Blueprint $table) {
                // $table->string('unique_key')->nullable();
            });
        }

        if (!Schema::hasColumn('lottery_tickets', 'unique_key')) {
            Schema::table('lottery_tickets', function (Blueprint $table) {
                // $table->string('unique_key')->nullable();
            });
        }

        // 4. Populate unique_key for existing SYSTEM records 
        DB::statement("UPDATE point_histories SET unique_key = 'ph_sys_' || account_id || '_' || month || '_' || year || '_' || type WHERE source = 'SYSTEM' AND unique_key IS NULL");
        DB::statement("UPDATE lottery_tickets SET unique_key = 'lt_sys_' || event_id || '_' || participant_id || '_' || month || '_' || year WHERE source = 'SYSTEM' AND unique_key IS NULL");

        // 5. Add unique index to the populated column
        Schema::table('point_histories', function (Blueprint $table) {
            // $table->unique('unique_key');
        });

        Schema::table('lottery_tickets', function (Blueprint $table) {
            // $table->unique('unique_key');
        });
    }

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
