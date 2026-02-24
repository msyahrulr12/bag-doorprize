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
        Schema::create('failed_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('local_path');
            $table->string('target_directory');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('failed'); // failed, retried, success
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_uploads');
    }
};
