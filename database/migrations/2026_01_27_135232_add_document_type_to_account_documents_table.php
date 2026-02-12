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
            $table->string('document_type')->nullable();
            $table->text('file_description')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_documents', function (Blueprint $table) {
            $table->dropColumn('document_type');
            $table->dropColumn('file_description');
        });
    }
};
