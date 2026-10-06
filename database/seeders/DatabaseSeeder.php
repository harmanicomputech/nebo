<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, InventoryReferenceSeeder::class]);

        if (! app()->isProduction()) {
            $this->call([DemoUsersSeeder::class, InventoryDemoSeeder::class]);
        }
    }
}
