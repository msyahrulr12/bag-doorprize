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
            $table->boolean('has_stored_to_sftp')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_documents', function (Blueprint $table) {
            $table->dropColumn('has_stored_to_sftp');
        });
    }
};
