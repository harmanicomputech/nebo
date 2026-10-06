<?php

namespace Database\Seeders;

use App\Models\Lookup;
use Illuminate\Database\Seeder;

/** Staff roles (brief §36). Idempotent and production-safe. */
class EventsReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['production_manager' => 'Production Manager', 'technical_director' => 'Technical Director', 'lighting_technician' => 'Lighting Technician',
            'sound_engineer' => 'Sound Engineer', 'camera_operator' => 'Camera Operator', 'livestream_operator' => 'Livestream Operator',
            'stage_manager' => 'Stage Manager', 'rigger' => 'Rigger', 'driver' => 'Driver', 'general_crew' => 'General Crew', 'administrator' => 'Administrator'];

        $i = 0;
        foreach ($roles as $key => $label) {
            Lookup::firstOrCreate(['group' => 'staff_role', 'key' => $key], ['label' => $label, 'sort_order' => (++$i) * 10, 'is_active' => true, 'is_system' => false]);
        }
    }
}
