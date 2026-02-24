<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
        CREATE VIEW report_points_view AS
        SELECT
            lottery_tickets.total_points,
            lottery_tickets.range_start,
            lottery_tickets.range_end,
            lottery_tickets.status as lottery_ticket_status,
            customers.cif,
            accounts.account_type,
            customer_branch.company_book as cif_branch_code,
            account_branch.company_book as account_branch_code,
            participants.participant_name,
            accounts.account_number,
            point_histories.description,
            point_histories.month as month,
            point_histories.year as year
        FROM lottery_tickets
        JOIN participants ON participants.id = lottery_tickets.participant_id
        JOIN accounts ON accounts.id = participants.account_id
        JOIN customers ON customers.id = accounts.customer_id
        JOIN branches as account_branch ON account_branch.id = accounts.branch_id
        JOIN branches as customer_branch ON customer_branch.id = customers.branch_id
        JOIN point_histories ON point_histories.account_id = accounts.id 
            AND point_histories.month = lottery_tickets.month 
            AND point_histories.year = lottery_tickets.year
        WHERE lottery_tickets.deleted_at IS NULL
        AND participants.deleted_at IS NULL
        AND point_histories.deleted_at IS NULL
        GROUP BY lottery_tickets.total_points, customers.cif, accounts.account_number, lottery_tickets.range_start, lottery_tickets.range_end, lottery_tickets.status, accounts.account_type, customer_branch.company_book, account_branch.company_book, participants.participant_name, point_histories.description, point_histories.month, point_histories.year;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS report_points_view");
    }
};
