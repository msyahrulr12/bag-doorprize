<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix for PostgreSQL participants table id column
        if (config('database.default') === 'pgsql') {
            DB::statement("ALTER TABLE participants ALTER COLUMN id SET DEFAULT nextval('participants_id_seq')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'pgsql') {
            DB::statement("ALTER TABLE participants ALTER COLUMN id DROP DEFAULT");
        }
    }
};
