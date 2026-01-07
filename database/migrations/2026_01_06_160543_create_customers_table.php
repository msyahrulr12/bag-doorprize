<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->string('name');
            $table->string('cif')->unique();
            $table->string('email')->unique();
            $table->string('phone_number', 15)->unique();
            $table->text('address')->nullable();
            $table->text('description')->nullable();
            $table->integer('total_point_sum')->default(0);
            $table->integer('redeemed_points')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
