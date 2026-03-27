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
            $table->string('participant_email')->nullable()->change();
            $table->string('participant_phone_number')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('winners', function (Blueprint $table) {
            $table->string('participant_email')->change();
            $table->string('participant_phone_number')->change();
        });
    }
};
