<?php

namespace Database\Seeders;

use App\Models\Lookup;
use Illuminate\Database\Seeder;

/** Vehicle types (brief §28). Idempotent and production-safe. */
class LogisticsReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['truck' => 'Truck', 'box_van' => 'Box van', 'van' => 'Van', 'pickup' => 'Pickup', 'bus' => 'Crew bus',
            'car' => 'Car', 'trailer' => 'Trailer', 'generator_truck' => 'Generator truck'];

        $i = 0;
        foreach ($types as $key => $label) {
            Lookup::firstOrCreate(['group' => 'vehicle_type', 'key' => $key], ['label' => $label, 'sort_order' => (++$i) * 10, 'is_active' => true, 'is_system' => false]);
        }
    }
}
