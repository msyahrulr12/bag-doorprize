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
        $configs = [
            ['table' => 'users', 'columns' => ['email'], 'index' => 'users_email_unique'],
            ['table' => 'winners', 'columns' => ['participant_id', 'event_prize_id'], 'index' => 'unique_winner_prize'],
        ];

        foreach ($configs as $config) {
            $table = $config['table'];
            $columns = implode(', ', $config['columns']);
            $indexName = $config['index'];

            // Drop constraint if exists
            DB::statement("
                DO $$ 
                BEGIN 
                    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = '{$indexName}') THEN
                        EXECUTE 'ALTER TABLE {$table} DROP CONSTRAINT {$indexName}';
                    END IF;
                END $$;
            ");

            // Drop index if exists
            DB::statement("DROP INDEX IF EXISTS {$indexName}");

            // Create partial unique index
            DB::statement("CREATE UNIQUE INDEX {$indexName} ON {$table} ({$columns}) WHERE (deleted_at IS NULL)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $configs = [
            ['table' => 'users', 'columns' => ['email'], 'index' => 'users_email_unique'],
            ['table' => 'winners', 'columns' => ['participant_id', 'event_prize_id'], 'index' => 'unique_winner_prize'],
        ];

        foreach ($configs as $config) {
            $table = $config['table'];
            $columns = implode(', ', $config['columns']);
            $indexName = $config['index'];

            DB::statement("DROP INDEX IF EXISTS {$indexName}");
            DB::statement("CREATE UNIQUE INDEX {$indexName} ON {$table} ({$columns})");
        }
    }
};
