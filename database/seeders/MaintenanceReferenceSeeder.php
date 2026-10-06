<?php

namespace Database\Seeders;

use App\Models\Lookup;
use Illuminate\Database\Seeder;

/** Maintenance types (brief §26). Idempotent and production-safe. */
class MaintenanceReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['inspection' => 'Inspection', 'preventive' => 'Preventive service', 'repair' => 'Repair', 'cleaning' => 'Cleaning',
            'calibration' => 'Calibration', 'safety_test' => 'Safety / PAT test', 'firmware' => 'Firmware update', 'lamp_replacement' => 'Lamp / LED replacement'];

        $i = 0;
        foreach ($types as $key => $label) {
            Lookup::firstOrCreate(['group' => 'maintenance_type', 'key' => $key], ['label' => $label, 'sort_order' => (++$i) * 10, 'is_active' => true, 'is_system' => false]);
        }
    }
}
