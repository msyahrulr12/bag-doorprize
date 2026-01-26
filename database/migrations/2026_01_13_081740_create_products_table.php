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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sk_produk', 6)->unique();
            $table->unsignedBigInteger('kode_group_produk')->index();
            $table->string('group_produk');
            $table->string('kode_produk', 6)->unique();
            $table->string('nama_produk');
            $table->string('nama_singkat_produk');
            $table->string('kode_sub_produk')->nullable();
            $table->string('nama_sub_produk');
            $table->string('gol_mas')->nullable();
            $table->date('date_time')->nullable();
            $table->date('batch_date')->nullable();
            $table->date('insert_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
