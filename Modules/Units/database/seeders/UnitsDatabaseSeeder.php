<?php

namespace Modules\Units\Database\Seeders;

use Illuminate\Database\Seeder;

class UnitsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DefaultUnitSeeder::class,
        ]);
    }
}
