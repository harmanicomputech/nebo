<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        if (! app()->isProduction()) {
            $this->call([DemoUsersSeeder::class, InventoryDemoSeeder::class, BookingDemoSeeder::class, EventsDemoSeeder::class, AllocationDemoSeeder::class, MaintenanceDemoSeeder::class, LogisticsDemoSeeder::class]);
        }
    }
}
