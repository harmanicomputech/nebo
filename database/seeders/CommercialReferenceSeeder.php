<?php

namespace Database\Seeders;

use App\Models\Lookup;
use Illuminate\Database\Seeder;

/** Customer types (brief §31). Idempotent and production-safe. */
class CommercialReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['corporate' => 'Corporate', 'agency' => 'Agency / planner', 'government' => 'Government', 'religious' => 'Religious organisation',
            'ngo' => 'NGO / foundation', 'education' => 'School / university', 'individual' => 'Individual', 'entertainment' => 'Artist / entertainment'];

        $i = 0;
        foreach ($types as $key => $label) {
            Lookup::firstOrCreate(['group' => 'customer_type', 'key' => $key], ['label' => $label, 'sort_order' => (++$i) * 10, 'is_active' => true, 'is_system' => false]);
        }
    }
}
