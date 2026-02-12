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
        Schema::create('approval_configs', function (Blueprint $table) {
            $table->id();
            $table->string('resource'); // e.g. 'CustomerResource', 'PointCorrection'
            $table->string('action'); // e.g. 'create', 'update', 'delete', 'all'
            $table->boolean('is_enabled')->default(true);
            $table->string('approver_role')->default('super_admin'); // Role that can approve
            $table->timestamps();

            $table->unique(['resource', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_configs');
    }
};
