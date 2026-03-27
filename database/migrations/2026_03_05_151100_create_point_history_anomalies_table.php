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
        Schema::create('point_history_anomalies', function (Blueprint $table) {
            $table->id();
            $table->string('cif')->nullable();
            $table->string('account_number')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->text('description')->nullable();
            $table->decimal('avg_balance', 20, 2)->default(0);
            $table->integer('year');
            $table->integer('month');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_history_anomalies');
    }
};
