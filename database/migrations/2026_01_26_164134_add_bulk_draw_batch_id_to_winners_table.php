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
        Schema::table('winners', function (Blueprint $table) {
            $table->unsignedBigInteger('bulk_draw_batch_id')->nullable()->index();
            $table->foreign('bulk_draw_batch_id')->references('id')->on('bulk_draw_batches')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('winners', function (Blueprint $table) {
            $table->dropForeign(['bulk_draw_batch_id']);
            $table->dropIndex(['bulk_draw_batch_id']);
            $table->dropColumn('bulk_draw_batch_id');
        });
    }
};
