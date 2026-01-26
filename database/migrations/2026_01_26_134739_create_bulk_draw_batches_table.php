<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bulk_draw_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_prize_id')->constrained()->cascadeOnDelete();
            $table->foreignId('draw_session_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('PENDING'); // PENDING, PROCESSING, COMPLETED, FAILED
            $table->integer('total_winners')->default(0);
            $table->integer('processed_winners')->default(0);
            $table->json('results')->nullable();
            $table->text('error_message')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_draw_batches');
    }
};
