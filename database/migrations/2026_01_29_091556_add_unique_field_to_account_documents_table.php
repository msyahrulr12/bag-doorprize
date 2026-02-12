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
            $table->unique(['customer_id', 'account_id', 'document_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_documents', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'account_id', 'document_type']);
        });
    }
};
