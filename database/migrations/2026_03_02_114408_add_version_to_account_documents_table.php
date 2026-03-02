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
        Schema::table('account_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('account_documents', 'version')) {
                $table->integer('version')->default(1)->after('period');
            }
            if (!Schema::hasColumn('account_documents', 'is_latest')) {
                $table->boolean('is_latest')->default(true)->after('version');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_documents', function (Blueprint $table) {
            $table->dropColumn(['version', 'is_latest']);
        });
    }
};
