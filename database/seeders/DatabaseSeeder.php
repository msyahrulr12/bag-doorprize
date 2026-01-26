<?php

namespace Database\Seeders;

use Modules\UserManagement\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ProductSeeder::class,
            BranchSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
