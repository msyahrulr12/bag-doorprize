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
        Schema::table('branches', function (Blueprint $table) {
            $table->string('sk_branch')->unique()->nullable();
            $table->string('sandi_pelapor_kantor_lbu')->nullable();
            $table->string('nama_sandi_pelapor')->nullable();
            $table->string('company_book')->nullable();
            $table->string('company_name')->nullable();
            $table->text('name_address')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->char('version')->nullable();
            $table->string('wib')->nullable();
            $table->date('update_date')->nullable();
            $table->string('update_regional1')->nullable();
            $table->date('update_date1')->nullable();
            $table->string('new_regional_head')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'sk_branch',
                'sandi_pelapor_kantor_lbu',
                'nama_sandi_pelapor',
                'company_book',
                'company_name',
                'name_address',
                'date_from',
                'date_to',
                'version',
                'wib',
                'update_date',
                'update_regional1',
                'update_date1',
                'new_regional_head',
            ]);
        });
    }
};
