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
        Schema::create('temporary_winners', function (Blueprint $table) {
            $table->id();

            // Participant Details
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->string('participant_cif')->index();
            $table->string('participant_account_number')->index();
            $table->string('participant_name');
            $table->string('participant_email')->nullable();
            $table->string('participant_phone_number')->nullable();

            // Event Prize
            $table->foreignId('event_prize_id')->constrained()->cascadeOnDelete();

            // Drawn Details
            $table->string('draw_session_id')->index();
            $table->string('winning_number');
            $table->dateTime('drawn_at');
            $table->string('drawn_by');

            // Lottery Ticket
            $table->foreignId('lottery_ticket_id')->constrained()->cascadeOnDelete();
            $table->integer('total_points')->default(0);
            $table->string('range_start')->nullable();
            $table->string('range_end')->nullable();

            // Status
            $table->string('status')->default('PENDING');

            // Branches
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('branch_code')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('branch_company_book')->nullable();
            $table->string('branch_region')->nullable();

            $table->string('account_status')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_winners');
    }
};
