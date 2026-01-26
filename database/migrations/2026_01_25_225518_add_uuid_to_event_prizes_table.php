<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_prizes', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id')->unique();
        });

        // Seed UUIDs for existing records
        $eventPrizes = \DB::table('event_prizes')->get();
        foreach ($eventPrizes as $ep) {
            \DB::table('event_prizes')->where('id', $ep->id)->update(['uuid' => (string) Str::uuid()]);
        }

        Schema::table('event_prizes', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_prizes', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
