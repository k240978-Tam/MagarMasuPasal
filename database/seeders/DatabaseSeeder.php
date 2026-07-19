<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tenancy\Database\Seeders\TenancyDatabaseSeeder;
use Modules\UserManagement\Database\Seeders\UserManagementDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database: platform reference data first
     * (business types, system role templates), then the first tenant.
     */
    public function run(): void
    {
        $this->call([
            TenancyDatabaseSeeder::class,
            UserManagementDatabaseSeeder::class,
            MagarMasuPasalSeeder::class,
        ]);
    }
}
