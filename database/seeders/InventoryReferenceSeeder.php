<?php

namespace Database\Seeders;

use App\Models\AssetStatus;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\Lookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Reference data the inventory needs. Safe in production and on every
 * deploy: it only adds what is missing and never overwrites labels or
 * settings administrators have changed.
 */
class InventoryReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->lookups();
        $this->statuses();

        // Starting categories and locations from the brief, only on a fresh install.
        if (EquipmentCategory::withTrashed()->doesntExist()) {
            $this->categories();
        }

        if (Location::withTrashed()->doesntExist()) {
            $this->locations();
        }
    }

    private function lookups(): void
    {
        $groups = [
            'condition' => [
                ['excellent', 'Excellent', []],
                ['good', 'Good', []],
                ['fair', 'Fair', []],
                ['requires_inspection', 'Requires Inspection', ['blocks_allocation' => true, 'sets_status' => 'under_inspection']],
                ['damaged', 'Damaged', ['blocks_allocation' => true, 'sets_status' => 'damaged']],
                ['critical', 'Critical', ['blocks_allocation' => true, 'sets_status' => 'damaged']],
            ],
            'location_type' => [
                ['warehouse', 'Warehouse', ['is_storage' => true]],
                ['production_site', 'Production site', []],
                ['client_site', 'Client site', []],
                ['transit', 'In transit', []],
                ['maintenance', 'Maintenance area', []],
                ['other', 'Other', []],
            ],
            'unit' => [
                ['unit', 'Unit', []], ['piece', 'Piece', []], ['pair', 'Pair', []], ['set', 'Set', []],
                ['metre', 'Metre', []], ['roll', 'Roll', []], ['box', 'Box', []], ['case', 'Case', []], ['kit', 'Kit', []],
            ],
        ];

        foreach ($groups as $group => $items) {
            foreach ($items as $i => [$key, $label, $meta]) {
                Lookup::firstOrCreate(
                    ['group' => $group, 'key' => $key],
                    ['label' => $label, 'sort_order' => ($i + 1) * 10, 'is_active' => true, 'is_system' => true, 'meta' => $meta ?: null],
                );
            }
        }
    }

    private function statuses(): void
    {
        // code, label, group, tone, allocatable, manual, description
        $statuses = [
            ['available', 'Available', 'available', 'success', true, true, 'In storage and ready to allocate.'],
            ['reserved', 'Reserved', 'committed', 'info', false, false, 'Held for an event; set by allocation.'],
            ['allocated', 'Allocated', 'committed', 'info', false, false, 'Assigned to an event; set by allocation.'],
            ['checked_out', 'Checked Out', 'out', 'warning', false, false, 'Left the warehouse for an event.'],
            ['in_transit', 'In Transit', 'out', 'warning', false, false, 'On the road to or from a venue.'],
            ['deployed', 'Deployed', 'out', 'dark', false, false, 'In use at an event.'],
            ['on_site', 'On Site', 'out', 'dark', false, false, 'At the venue, not yet in use.'],
            ['under_inspection', 'Under Inspection', 'attention', 'warning', false, true, 'Being checked before it can go out again.'],
            ['maintenance_required', 'Maintenance Required', 'attention', 'warning', false, true, 'Needs repair or service before use.'],
            ['under_maintenance', 'Under Maintenance', 'attention', 'warning', false, true, 'With a technician.'],
            ['damaged', 'Damaged', 'damaged', 'danger', false, true, 'Damaged and out of service.'],
            ['lost', 'Lost', 'lost', 'danger', false, true, 'Missing; not returned or cannot be found.'],
            ['retired', 'Retired', 'retired', 'neutral', false, true, 'Permanently out of service; history kept.'],
            ['unavailable', 'Unavailable', 'unavailable', 'neutral', false, true, 'Held back for another reason.'],
        ];

        foreach ($statuses as $i => [$code, $label, $group, $tone, $allocatable, $manual, $description]) {
            AssetStatus::firstOrCreate(['code' => $code], [
                'label' => $label, 'group' => $group, 'tone' => $tone, 'description' => $description,
                'is_allocatable' => $allocatable, 'is_manual' => $manual, 'is_system' => true, 'is_active' => true,
                'sort_order' => ($i + 1) * 10,
            ]);
        }
    }

    private function categories(): void
    {
        $categories = [
            ['Moving Lights', 'lightbulb', ['Spot / Profile', 'Wash', 'Beam']],
            ['Conventionals and LEDs', 'lamp', ['Profiles', 'PARs', 'LED Battens']],
            ['Followspots', 'flashlight', []],
            ['Consoles', 'sliders-horizontal', ['Lighting Consoles', 'Audio Consoles']],
            ['Special FX', 'sparkles', ['Haze & Smoke', 'Pyro & CO2', 'Confetti']],
            ['Distro', 'plug-zap', []],
            ['Trussing', 'route', []],
            ['Dimmers', 'gauge', []],
            ['Rigging', 'anchor', ['Hoists', 'Shackles & Slings']],
            ['Cables', 'cable', ['Power', 'Data / DMX', 'Audio']],
            ['Road Cases', 'package', []],
            ['Communications', 'radio', []],
        ];

        foreach ($categories as $i => [$name, $icon, $children]) {
            $parent = EquipmentCategory::create(['name' => $name, 'slug' => Str::slug($name), 'icon' => $icon, 'sort_order' => ($i + 1) * 10, 'is_active' => true]);

            foreach ($children as $j => $child) {
                EquipmentCategory::create(['parent_id' => $parent->id, 'name' => $child, 'slug' => Str::slug($name.' '.$child), 'sort_order' => ($j + 1) * 10, 'is_active' => true]);
            }
        }
    }

    private function locations(): void
    {
        foreach ([
            ['Main Warehouse', 'MAIN', 'warehouse'],
            ['Secondary Warehouse', 'SEC', 'warehouse'],
            ['Maintenance Area', 'MAINT', 'maintenance'],
            ['In Transit', 'TRANSIT', 'transit'],
        ] as [$name, $code, $type]) {
            Location::create(['name' => $name, 'code' => $code, 'type' => $type, 'is_active' => true]);
        }
    }
}
