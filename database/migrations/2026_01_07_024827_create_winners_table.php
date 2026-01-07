<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('winners', function (Blueprint $table) {
            $table->id();

            // Participant Details
            $table->unsignedBigInteger('participant_id');
            $table->foreign('participant_id')->references('id')->on('participants')->onDelete('cascade');
            $table->string('participant_cif');
            $table->string('participant_account_number');
            $table->string('participant_name');
            $table->string('participant_email');
            $table->string('participant_phone_number');

            // Event Prize
            $table->unsignedBigInteger('event_prize_id');
            $table->foreign('event_prize_id')->references('id')->on('event_prizes')->onDelete('cascade');

            // Prize Details
            $table->string('prize_name');
            $table->string('prize_tier');
            $table->integer('prize_total_quantity')->default(1);
            $table->decimal('prize_value', 15, 2)->nullable();
            $table->string('prize_description')->nullable();

            // Event Details
            $table->string('event_code');
            $table->string('event_name');

            // Drawn Details
            $table->unsignedBigInteger('draw_session_id');
            $table->foreign('draw_session_id')->references('id')->on('draw_sessions')->onDelete('cascade');
            $table->integer('winning_number')->nullable();
            $table->timestamp('drawn_at')->useCurrent();
            $table->string('drawn_by');

            // Lottery Ticket Details
            $table->unsignedBigInteger('lottery_ticket_id');
            $table->foreign('lottery_ticket_id')->references('id')->on('lottery_tickets')->onDelete('cascade');
            $table->integer('total_points')->default(0);
            $table->integer('range_start')->default(0);
            $table->integer('range_end')->default(0);

            // Claim Details
            $table->string('status')->nullable();
            $table->string('claimed_by')->nullable();
            $table->timestamp('claimed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['participant_id', 'event_prize_id'], 'unique_winner_prize');
            $table->index(['participant_id', 'event_prize_id', 'draw_session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('winners');
    }
};
