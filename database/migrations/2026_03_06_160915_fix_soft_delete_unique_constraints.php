<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'products' => ['sk_produk', 'kode_produk'],
            'prizes' => ['prize_code'],
            'branches' => ['branch_code'],
            'events' => ['event_code'],
            'customers' => ['cif', 'email', 'phone_number'],
            'accounts' => ['account_number'],
            'settings' => ['key'],
        ];

        foreach ($tables as $table => $columns) {
            foreach ($columns as $column) {
                $indexName = "{$table}_{$column}_unique";

                // First try to drop as a constraint
                DB::statement("
                    DO $$ 
                    BEGIN 
                        IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = '{$indexName}') THEN
                            EXECUTE 'ALTER TABLE {$table} DROP CONSTRAINT {$indexName}';
                        END IF;
                    END $$;
                ");

                // Then try to drop as an index (if it wasn't a constraint)
                DB::statement("DROP INDEX IF EXISTS {$indexName}");

                // Create partial unique index
                DB::statement("CREATE UNIQUE INDEX {$indexName} ON {$table} ({$column}) WHERE (deleted_at IS NULL)");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'products' => ['sk_produk', 'kode_produk'],
            'prizes' => ['prize_code'],
            'branches' => ['branch_code'],
            'events' => ['event_code'],
            'customers' => ['cif', 'email', 'phone_number'],
            'accounts' => ['account_number'],
            'settings' => ['key'],
        ];

        foreach ($tables as $table => $columns) {
            foreach ($columns as $column) {
                $indexName = "{$table}_{$column}_unique";

                DB::statement("DROP INDEX IF EXISTS {$indexName}");

                // Revert to standard unique (no partial)
                DB::statement("CREATE UNIQUE INDEX {$indexName} ON {$table} ({$column})");
            }
        }
    }
};
