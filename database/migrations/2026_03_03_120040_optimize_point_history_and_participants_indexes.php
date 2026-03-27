<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add Source column for differentiation
        if (!Schema::hasColumn('point_histories', 'source')) {
            Schema::table('point_histories', function (Blueprint $table) {
                $table->string('source')->default('SYSTEM')->index();
            });
        }
        if (!Schema::hasColumn('lottery_tickets', 'source')) {
            Schema::table('lottery_tickets', function (Blueprint $table) {
                $table->string('source')->default('SYSTEM')->index();
            });
        }

        // 1. Clean up PointHistory duplicates based on the new unique key
        DB::statement("
            DELETE FROM point_histories h1
            USING point_histories h2
            WHERE h1.id < h2.id
            AND h1.account_id = h2.account_id
            AND h1.month = h2.month
            AND h1.year = h2.year
            AND h1.type = h2.type
            AND h1.source = h2.source
        ");

        Schema::table('point_histories', function (Blueprint $table) {
            try {
                $table->unique(['account_id', 'month', 'year', 'type', 'source'], 'unique_ph_full_key');
            } catch (\Exception $e) {
            }

            // Clean up the temporary index from previous failed attempt if exists
            try {
                $indices = DB::select("SELECT indexname FROM pg_indexes WHERE indexname = 'unique_ph_acc_mo_yr_type'");
                if (!empty($indices)) {
                    $table->dropUnique('unique_ph_acc_mo_yr_type');
                }
            } catch (\Exception $e) {
            }
        });

        // 2. Participants unique key cleanup
        DB::statement("
            DELETE FROM participants p1
            USING participants p2
            WHERE p1.id < p2.id
            AND p1.event_id = p2.event_id
            AND p1.account_id = p2.account_id
        ");

        Schema::table('participants', function (Blueprint $table) {
            try {
                $indices = DB::select("SELECT indexname FROM pg_indexes WHERE indexname = 'unique_part_event_acc'");
                if (empty($indices)) {
                    $table->unique(['event_id', 'account_id'], 'unique_part_event_acc');
                }
            } catch (\Exception $e) {
                try {
                    $table->unique(['event_id', 'account_id'], 'unique_part_event_acc');
                } catch (\Exception $ex) {
                }
            }
        });

        // 3. Lottery Tickets unique key cleanup
        DB::statement("
            DELETE FROM lottery_tickets l1
            USING lottery_tickets l2
            WHERE l1.id < l2.id
            AND l1.event_id = l2.event_id
            AND l1.participant_id = l2.participant_id
            AND l1.month = l2.month
            AND l1.year = l2.year
            AND l1.source = l2.source
        ");

        Schema::table('lottery_tickets', function (Blueprint $table) {
            try {
                $table->unique(['event_id', 'participant_id', 'month', 'year', 'source'], 'unique_lt_full_key');
            } catch (\Exception $e) {
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('point_histories', function (Blueprint $table) {
            $table->dropUnique('unique_ph_full_key');
            $table->dropColumn('source');
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->dropUnique('unique_part_event_acc');
        });

        Schema::table('lottery_tickets', function (Blueprint $table) {
            $table->dropUnique('unique_lt_full_key');
            $table->dropColumn('source');
        });
    }
};
