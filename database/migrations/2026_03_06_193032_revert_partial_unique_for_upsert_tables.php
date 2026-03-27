<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Revert partial unique indexes back to standard unique indexes for tables
 * that use Laravel's upsert() method (e.g. in ProcessPointHistory job).
 *
 * PostgreSQL's ON CONFLICT does not match partial unique indexes automatically,
 * so upsert() fails with "no unique or exclusion constraint matching the ON CONFLICT".
 *
 * Tables like products, prizes, branches, events, settings keep partial indexes
 * because they benefit from soft-delete + re-create with the same key via the UI.
 */
return new class extends Migration {
    public function up(): void
    {
        // Tables where upsert() is used and need standard (non-partial) unique indexes
        $tables = [
            'customers' => ['cif', 'email', 'phone_number'],
            'accounts' => ['account_number'],
        ];

        foreach ($tables as $table => $columns) {
            foreach ($columns as $column) {
                $indexName = "{$table}_{$column}_unique";

                // Drop the partial unique index
                DB::statement("DROP INDEX IF EXISTS {$indexName}");

                // Re-create as a standard unique index (no WHERE clause)
                // Use a constraint so that ON CONFLICT works properly
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$indexName} UNIQUE ({$column})");
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'customers' => ['cif', 'email', 'phone_number'],
            'accounts' => ['account_number'],
        ];

        foreach ($tables as $table => $columns) {
            foreach ($columns as $column) {
                $indexName = "{$table}_{$column}_unique";

                // Drop constraint
                DB::statement("
                    DO $$ 
                    BEGIN 
                        IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = '{$indexName}') THEN
                            EXECUTE 'ALTER TABLE {$table} DROP CONSTRAINT {$indexName}';
                        END IF;
                    END $$;
                ");

                DB::statement("DROP INDEX IF EXISTS {$indexName}");

                // Re-create as partial unique index
                DB::statement("CREATE UNIQUE INDEX {$indexName} ON {$table} ({$column}) WHERE (deleted_at IS NULL)");
            }
        }
    }
};
