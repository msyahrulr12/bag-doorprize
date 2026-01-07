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
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->unsignedBigInteger('account_id');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->string('participant_name');
            $table->string('participant_cif');
            $table->string('participant_account_number');
            $table->string('participant_email');
            $table->string('participant_phone_number');
            $table->integer('total_points_snapshot')->default(0);
            $table->integer('range_start')->nullable();
            $table->integer('range_end')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'account_id', 'participant_cif', 'participant_account_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
