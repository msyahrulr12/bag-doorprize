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
        /**
         * =========================
         * lottery_tickets
         * =========================
         */
        Schema::table('lottery_tickets', function (Blueprint $table) {
            // JOIN ke participants
            $table->index('participant_id', 'idx_lt_participant_id');

            // Filter tahun / range date
            $table->index('created_at', 'idx_lt_created_at');

            // Optional: filter date + join
            $table->index(['created_at', 'participant_id'], 'idx_lt_date_participant');
        });

        /**
         * =========================
         * participants
         * =========================
         */
        Schema::table('participants', function (Blueprint $table) {
            // JOIN ke accounts
            $table->index('account_id', 'idx_participants_account_id');

            // Filter event + join
            $table->index(['event_id', 'account_id'], 'idx_participants_event_account');

            // (Index lama kamu, BIARKAN jika memang dipakai search)
            // $table->index(['event_id', 'account_id', 'participant_cif', 'participant_account_number']);
        });

        /**
         * =========================
         * accounts
         * =========================
         */
        Schema::table('accounts', function (Blueprint $table) {
            // JOIN ke customers
            $table->index('customer_id', 'idx_accounts_customer_id');

            // JOIN ke branches
            $table->index('branch_id', 'idx_accounts_branch_id');
        });

        /**
         * =========================
         * customers
         * =========================
         */
        Schema::table('customers', function (Blueprint $table) {
            // JOIN ke branches
            $table->index('branch_id', 'idx_customers_branch_id');
        });

        /**
         * =========================
         * point_histories
         * =========================
         */
        Schema::table('point_histories', function (Blueprint $table) {
            // JOIN ke accounts
            $table->index('account_id', 'idx_point_histories_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lottery_tickets', function (Blueprint $table) {
            $table->dropIndex('idx_lt_participant_id');
            $table->dropIndex('idx_lt_created_at');
            $table->dropIndex('idx_lt_date_participant');
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->dropIndex('idx_participants_account_id');
            $table->dropIndex('idx_participants_event_account');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex('idx_accounts_customer_id');
            $table->dropIndex('idx_accounts_branch_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_branch_id');
        });

        Schema::table('point_histories', function (Blueprint $table) {
            $table->dropIndex('idx_point_histories_account_id');
        });
    }
};
