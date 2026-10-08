<?php

namespace App\Services\Inventory;

use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Nebo Stage's own equipment: the stage and LED screen inventory of 2026
 * (D73). Real records, not sample data: clearing sample data keeps them.
 * Imported once, through the inventory services so every unit and quantity
 * has an opening ledger entry; skipped if any of these items already exist.
 */
class InitialInventory
{
    /** Categories: parent => [icon, [children]]. Existing ones (from the starting set) are reused. */
    private const CATEGORIES = [
        'LED Screens' => ['monitor', ['LED Panels', 'Video Processors', 'Mounting']],
        'Trussing' => ['route', ['400 × 600 Spigot Truss', '400 × 400 Spigot Truss', 'Roof System', 'Connectors & Pins']],
        'Rigging' => ['anchor', ['Hoists', 'Shackles & Slings']],
        'Staging' => ['layers', ['Stage Decks', 'Steps', 'Frames & Fittings']],
    ];

    /**
     * sku => [name, [parent, child], manufacturer, model, tracking, unit, quantity, asset prefix, description]
     */
    public const ITEMS = [
        'LED-P391-S' => ['P3.91 Outdoor LED Panel 0.5 m × 0.5 m', ['LED Screens', 'LED Panels'], 'CY', 'P3.91 outdoor 500 × 500', 'serialized', 'unit', 48, 'LP5',
            'Outdoor LED panel, 3.91 mm pixel pitch, 0.5 m × 0.5 m. 48 panels make 12 m² of screen.'],
        'LED-P391-L' => ['P3.91 Outdoor LED Panel 0.5 m × 1 m', ['LED Screens', 'LED Panels'], 'CY', 'P3.91 outdoor 500 × 1000', 'serialized', 'unit', 24, 'LP10',
            'Outdoor LED panel, 3.91 mm pixel pitch, 0.5 m × 1 m. 24 panels make 12 m² of screen.'],
        'VID-VX600' => ['Video Processor VX600 Pro', ['LED Screens', 'Video Processors'], 'NovaStar', 'VX600 Pro', 'serialized', 'unit', 3, 'VXP',
            'All-in-one LED video controller and processor for the screens.'],
        'LED-HOOK' => ['LED Screen Hanging Hook', ['LED Screens', 'Mounting'], null, null, 'bulk', 'piece', 18, null,
            'Hooks for hanging the LED screen from truss.'],

        'TRS-46-3M' => ['Truss 400 × 600 — 3 m', ['Trussing', '400 × 600 Spigot Truss'], null, 'Aluminium spigot truss, 400 × 600 mm, 3 m', 'bulk', 'piece', 20, null, null],
        'TRS-46-2M' => ['Truss 400 × 600 — 2 m', ['Trussing', '400 × 600 Spigot Truss'], null, 'Aluminium spigot truss, 400 × 600 mm, 2 m', 'bulk', 'piece', 2, null, null],
        'TRS-46-1M' => ['Truss 400 × 600 — 1 m', ['Trussing', '400 × 600 Spigot Truss'], null, 'Aluminium spigot truss, 400 × 600 mm, 1 m', 'bulk', 'piece', 2, null, null],
        'TRS-44-3M' => ['Truss 400 × 400 — 3 m', ['Trussing', '400 × 400 Spigot Truss'], null, 'Aluminium spigot truss, 400 × 400 mm, 3 m', 'bulk', 'piece', 20, null, null],
        'TRS-44-2M' => ['Truss 400 × 400 — 2 m', ['Trussing', '400 × 400 Spigot Truss'], null, 'Aluminium spigot truss, 400 × 400 mm, 2 m', 'bulk', 'piece', 21, null, null],
        'TRS-44-1M5' => ['Truss 400 × 400 — 1.5 m', ['Trussing', '400 × 400 Spigot Truss'], null, 'Aluminium spigot truss, 400 × 400 mm, 1.5 m', 'bulk', 'piece', 2, null, null],
        'TRS-44-1M' => ['Truss 400 × 400 — 1 m', ['Trussing', '400 × 400 Spigot Truss'], null, 'Aluminium spigot truss, 400 × 400 mm, 1 m', 'bulk', 'piece', 7, null, null],

        'ROOF-SLEEVE' => ['Sleeve Block', ['Trussing', 'Roof System'], null, null, 'bulk', 'piece', 6, null, 'Lets the roof grid climb the truss towers.'],
        'ROOF-HINGE' => ['Hinge', ['Trussing', 'Roof System'], null, null, 'bulk', 'piece', 24, null, null],
        'ROOF-TOP' => ['Top Section', ['Trussing', 'Roof System'], null, null, 'bulk', 'piece', 6, null, 'Tower head for the roof system.'],
        'ROOF-BASE' => ['Steel Pipe Base with Extension Feet', ['Trussing', 'Roof System'], null, 'Welded steel pipe', 'bulk', 'piece', 6, null, 'Tower base with adjustable feet.'],
        'ROOF-SLANT' => ['Aluminium Slant Support 50 × 1800', ['Trussing', 'Roof System'], null, '50 × 1800 mm', 'bulk', 'piece', 12, null, null],
        'ROOF-ADAPT' => ['Top Multi-directional Adapter', ['Trussing', 'Roof System'], null, null, 'bulk', 'piece', 2, null, null],
        'ROOF-SLOPE' => ['Customised Downhill Slope', ['Trussing', 'Roof System'], null, null, 'bulk', 'piece', 4, null, 'Gives the roof its fall for rain run-off.'],

        'RIG-HOIST' => ['Manual Chain Hoist (galvanised)', ['Rigging', 'Hoists'], null, 'Galvanised chain hoist', 'serialized', 'unit', 6, 'HST',
            'Hand chain hoist for lifting the roof grid and screens.'],
        'RIG-STRAP' => ['Lifting Strap 2 t × 3 m', ['Rigging', 'Shackles & Slings'], null, '2 tonne, 3 m', 'bulk', 'piece', 6, null, null],

        'TRS-FAST' => ['Fasteners and Joints', ['Trussing', 'Connectors & Pins'], null, null, 'bulk', 'piece', 8, null, null],
        'TRS-EGG' => ['Egg-shaped Connector', ['Trussing', 'Connectors & Pins'], null, null, 'bulk', 'piece', 350, null, 'Spigot connectors for joining truss.'],
        'TRS-PIN' => ['Pin with R-clip', ['Trussing', 'Connectors & Pins'], null, null, 'bulk', 'piece', 900, null, 'Spigot pins with R-type safety clips.'],

        'STG-RACK' => ['Single Rack 300 mm wide', ['Staging', 'Frames & Fittings'], null, '300 mm wide', 'bulk', 'piece', 1, null, null],
        'STG-BUCKLE' => ['Aluminium Single Buckle', ['Staging', 'Frames & Fittings'], null, null, 'bulk', 'piece', 24, null, null],
        'STG-PANEL' => ['Stage Panel', ['Staging', 'Stage Decks'], null, null, 'bulk', 'piece', 50, null, null],
        'STG-STAIR' => ['Staircase', ['Staging', 'Steps'], null, null, 'bulk', 'piece', 2, null, 'Stage access steps.'],
    ];

    public function __construct(
        private EquipmentService $equipment,
        private AssetService $assets,
        private StockService $stock,
    ) {}

    public function alreadyImported(): bool
    {
        return Equipment::withTrashed()->whereIn('sku', array_keys(self::ITEMS))->exists();
    }

    /** @return int number of items added (0 when already imported) */
    public function import(): int
    {
        if ($this->alreadyImported()) {
            return 0;
        }

        $store = Location::where('code', 'MAIN')->first()
            ?? Location::query()->where('is_active', true)->orderBy('id')->firstOrFail();

        return DB::transaction(function () use ($store) {
            $categories = $this->categories();

            foreach (self::ITEMS as $sku => [$name, [$parent, $child], $make, $model, $tracking, $unit, $quantity, $prefix, $description]) {
                $item = $this->equipment->create([
                    'category_id' => $categories["{$parent} / {$child}"], 'name' => $name, 'sku' => $sku,
                    'manufacturer' => $make, 'model' => $model, 'tracking_mode' => $tracking, 'unit' => $unit,
                    'asset_prefix' => $prefix, 'description' => $description,
                ]);

                if ($tracking === 'serialized') {
                    $this->assets->register($item, ['location_id' => $store->id, 'condition' => 'excellent', 'notes' => 'New equipment, 2026 inventory.'], $quantity);
                } else {
                    $this->stock->receive($item, $store, $quantity, true, 'Opening stock: 2026 inventory');
                }
            }

            return count(self::ITEMS);
        });
    }

    /** @return array<string, int> "Parent / Child" => category id */
    private function categories(): array
    {
        $ids = [];
        $order = (int) EquipmentCategory::withTrashed()->whereNull('parent_id')->max('sort_order');

        foreach (self::CATEGORIES as $parentName => [$icon, $children]) {
            $parent = EquipmentCategory::withTrashed()->firstOrCreate(['slug' => Str::slug($parentName)], [
                'name' => $parentName, 'icon' => $icon, 'sort_order' => $order += 10, 'is_active' => true,
            ]);
            $parent->trashed() && $parent->restore();

            foreach ($children as $j => $childName) {
                $child = EquipmentCategory::withTrashed()->firstOrCreate(['slug' => Str::slug($parentName.' '.$childName)], [
                    'parent_id' => $parent->id, 'name' => $childName, 'sort_order' => ($j + 1) * 10, 'is_active' => true,
                ]);
                $child->trashed() && $child->restore();
                $ids["{$parentName} / {$childName}"] = $child->id;
            }
        }

        return $ids;
    }
}
