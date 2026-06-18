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
        Schema::table('event_prizes', function (Blueprint $table) {
            $table->unsignedBigInteger('draw_session_id')->nullable()->after('split_draw');

            $table->foreign('draw_session_id')->onDelete('restrict')->references('id')->on('draw_sessions')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_prizes', function (Blueprint $table) {
            $table->dropForeign(['draw_session_id']);
            $table->dropColumn('draw_session_id');
        });
    }
};
