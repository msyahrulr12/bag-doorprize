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
            $table->string('region')->nullable()->after('branch_name');
        });

        // Attempt to auto-map regions based on address
        $mapping = [
            'Jawa' => ['JAKARTA', 'JAWA', 'BANDUNG', 'SURABAYA', 'SEMARANG', 'TANGERANG', 'BOSER', 'BANTEN', 'CIREBON', 'BOGOR', 'DEPOK', 'BEKASI', 'YOGYAKARTA', 'SOLO', 'KOLAT'],
            'Sumatera' => ['SUMATERA', 'MEDAN', 'PALEMBANG', 'LAMPUNG', 'RIAU', 'BATAM', 'JAMBI', 'ACEH', 'PADANG', 'BENGKULU'],
            'Sulawesi' => ['SULAWESI', 'MANADO', 'MAKASSAR', 'PALU', 'KENDARI', 'GORONTALO'],
        ];

        foreach ($mapping as $region => $keywords) {
            foreach ($keywords as $keyword) {
                \DB::table('branches')
                    ->where('name_address', 'LIKE', "%{$keyword}%")
                    ->whereNull('region')
                    ->update(['region' => $region]);
            }
        }

        // Default the rest to 'Lainnya'
        \DB::table('branches')
            ->whereNull('region')
            ->update(['region' => 'Lainnya']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }
};
