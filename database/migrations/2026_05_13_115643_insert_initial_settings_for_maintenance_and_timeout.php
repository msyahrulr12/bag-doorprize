<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            [
                'group' => 'general',
                'key' => 'filament_idle_warning_timeout',
                'value' => '30',
                'type' => 'integer',
                'created_at' => '2026-05-12 15:13:43',
                'updated_at' => '2026-05-12 15:36:38',
            ],
            [
                'group' => 'general',
                'key' => 'filament_idle_timeout',
                'value' => '1800',
                'type' => 'integer',
                'created_at' => '2026-05-12 15:13:28',
                'updated_at' => '2026-05-13 09:18:28',
            ],
            [
                'group' => 'general',
                'key' => 'application_locked',
                'value' => '0',
                'type' => 'boolean',
                'created_at' => '2026-05-13 11:48:53',
                'updated_at' => '2026-05-13 11:48:53',
            ],
            [
                'group' => 'general',
                'key' => 'application_locked_excluded_emails',
                'value' => null,
                'type' => 'string',
                'created_at' => '2026-05-13 11:49:13',
                'updated_at' => '2026-05-13 11:49:13',
            ],
            [
                'group' => 'general',
                'key' => 'application_locked_excluded_roles',
                'value' => 'Program Owner,Siskon Operator,super_admin',
                'type' => 'string',
                'created_at' => '2026-05-13 11:49:22',
                'updated_at' => '2026-05-13 11:49:22',
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'filament_idle_warning_timeout',
            'filament_idle_timeout',
            'application_locked',
            'application_locked_excluded_emails',
            'application_locked_excluded_roles'
        ])->delete();
    }
};
