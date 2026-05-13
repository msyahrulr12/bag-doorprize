<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL for Postgres to handle the cast from string to bigint
        DB::statement('ALTER TABLE temporary_winners ALTER COLUMN draw_session_id TYPE bigint USING draw_session_id::bigint');
        
        Schema::table('temporary_winners', function (Blueprint $table) {
            $table->foreign('draw_session_id')->references('id')->on('draw_sessions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temporary_winners', function (Blueprint $table) {
            $table->dropForeign(['draw_session_id']);
        });

        DB::statement('ALTER TABLE temporary_winners ALTER COLUMN draw_session_id TYPE varchar USING draw_session_id::varchar');
    }
};
