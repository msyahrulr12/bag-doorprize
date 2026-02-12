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
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->string('model_type')->nullable(); // For resource models
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('resource')->nullable(); // Filament Resource or Page class
            $table->string('action'); // create, update, delete, or custom action
            $table->json('original_data')->nullable();
            $table->json('new_data')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Requester
            $table->foreignId('approver_id')->nullable()->constrained('users'); // Approver
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->text('reason')->nullable(); // Rejection reason
            $table->timestamp('action_at')->nullable(); // When approved/rejected
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
