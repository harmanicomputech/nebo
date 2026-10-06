<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * All production-safe reference data, for every module. Run on every deploy:
 *   php artisan db:seed --class=ReferenceDataSeeder --force
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            InventoryReferenceSeeder::class,
            BookingReferenceSeeder::class,
            EventsReferenceSeeder::class,
            MaintenanceReferenceSeeder::class,
            LogisticsReferenceSeeder::class,
            CommercialReferenceSeeder::class,
        ]);
    }
}
