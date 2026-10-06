<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Demo data in dependency order (also run, step by step, by the web installer). */
    public const DEMO = [DemoUsersSeeder::class, InventoryDemoSeeder::class, BookingDemoSeeder::class, EventsDemoSeeder::class, HistoryDemoSeeder::class, AllocationDemoSeeder::class, MaintenanceDemoSeeder::class, LogisticsDemoSeeder::class, CommercialDemoSeeder::class];

    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        if (! app()->isProduction()) {
            $this->call(self::DEMO);
        }
    }
}
