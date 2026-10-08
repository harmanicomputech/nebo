<?php

namespace Database\Seeders;

use App\Services\Inventory\InitialInventory;
use App\Support\SampleData;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Sample data in dependency order (also run, step by step, by the web installer). Removable from Settings → System (D71). */
    public const DEMO = [DemoUsersSeeder::class, BookingDemoSeeder::class, EventsDemoSeeder::class, HistoryDemoSeeder::class, AllocationDemoSeeder::class, MaintenanceDemoSeeder::class, LogisticsDemoSeeder::class, CommercialDemoSeeder::class];

    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);
        app(InitialInventory::class)->import(); // the company's real equipment (D73), not sample data

        if (! app()->isProduction()) {
            SampleData::record(fn () => $this->call(self::DEMO));
        }
    }
}
