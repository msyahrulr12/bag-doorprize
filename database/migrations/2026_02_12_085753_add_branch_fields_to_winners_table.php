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
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->index('branch_id');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->string('branch_code')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('branch_company_book')->nullable();
            $table->string('branch_region')->nullable();
        });
    }

    /**
     * Reverse the migrations.  
     */
    public function down(): void
    {
        Schema::table('winners', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['branch_id']);
            $table->dropColumn(['branch_id', 'branch_code', 'branch_name', 'branch_company_book', 'branch_region']);
        });
    }
};
