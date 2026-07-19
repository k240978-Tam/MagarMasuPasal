<?php

namespace Modules\UserManagement\Database\Seeders;

use Illuminate\Database\Seeder;

class UserManagementDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
        ]);
    }
}
