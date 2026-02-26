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
            $table->string('file_name_t24')->nullable();
            $table->string('file_path_t24')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_documents', function (Blueprint $table) {
            $table->dropColumn(['file_name_t24', 'file_path_t24']);
        });
    }
};
