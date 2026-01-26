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
        Schema::create('account_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type')->index(); // e.g., e-statement, contract, etc.
            $table->string('filename');
            $table->string('path');
            $table->date('period')->index(); // The month/year the document belongs to
            $table->boolean('is_merged')->default(false);
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->json('metadata')->nullable(); // For storing server paths, merging logs, etc.
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_documents');
    }
};
