<?php

namespace Database\Seeders;

use App\Enums\StockBucket;
use App\Models\AssetStatus;
use App\Models\Equipment;
use App\Models\EquipmentAsset;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\User;
use App\Services\Inventory\AssetService;
use App\Services\Inventory\EquipmentService;
use App\Services\Inventory\StockService;
use Illuminate\Database\Seeder;

/**
 * Demo equipment for development (never production). Built through the
 * inventory services so every unit has real ledger history. All SKUs start
 * with NS- so the data is easy to identify and remove.
 */
class InventoryDemoSeeder extends Seeder
{
    public function run(EquipmentService $equipment, AssetService $assets, StockService $stock): void
    {
        if (Equipment::where('sku', 'NS-ML-01')->exists()) {
            return;
        }

        $consumables = EquipmentCategory::firstOrCreate(['slug' => 'consumables'], ['name' => 'Consumables', 'icon' => 'package-check', 'sort_order' => 130, 'is_active' => true]);
        $cat = fn (string $slug) => EquipmentCategory::where('slug', $slug)->value('id') ?? $consumables->id;
        $main = Location::where('code', 'MAIN')->first();
        $secondary = Location::where('code', 'SEC')->first();
        $maintenanceArea = Location::where('code', 'MAINT')->first();
        $admin = User::where('email', 'ada.okafor@nebostage.com')->first() ?? User::first();

        // name, category slug, manufacturer, model, prefix, units, value (naira)
        $serialized = [
            ['Robe MegaPointe', 'moving-lights-spot-profile', 'Robe', 'MegaPointe', 'ML', 24, 9_500_000],
            ['Chauvet Maverick MK2 Wash', 'moving-lights-wash', 'Chauvet Professional', 'Maverick MK2 Wash', 'MW', 16, 6_800_000],
            ['ETC Source Four 750W', 'conventionals-and-leds-profiles', 'ETC', 'Source Four 26°', 'S4', 20, 850_000],
            ['Robert Juliat Cyrano 2500W', 'followspots', 'Robert Juliat', 'Cyrano 1013', 'FS', 4, 14_000_000],
            ['grandMA3 full-size', 'consoles-lighting-consoles', 'MA Lighting', 'grandMA3 full-size', 'GMA', 2, 75_000_000],
            ['Yamaha CL5', 'consoles-audio-consoles', 'Yamaha', 'CL5', 'CL', 2, 38_000_000],
            ['MDG theONE Hazer', 'special-fx-haze-smoke', 'MDG', 'theONE', 'HZ', 4, 4_200_000],
            ['125A Power Distro', 'distro', 'Nebo Fabrication', 'PD-125', 'DIS', 6, 2_100_000],
            ['ETC Sensor3 Dimmer Rack', 'dimmers', 'ETC', 'Sensor3 SR48', 'DIM', 3, 12_500_000],
            ['CM Lodestar 1T Chain Hoist', 'rigging-hoists', 'Columbus McKinnon', 'Lodestar 1T', 'HST', 12, 3_400_000],
            ['Clear-Com FreeSpeak II Beltpack', 'communications', 'Clear-Com', 'FSII-BP19', 'COM', 10, 1_150_000],
        ];

        foreach ($serialized as $i => [$name, $slug, $make, $model, $prefix, $units, $naira]) {
            $item = $equipment->create([
                'category_id' => $cat($slug), 'name' => $name, 'sku' => sprintf('NS-%s-%02d', $prefix, $i + 1),
                'manufacturer' => $make, 'model' => $model, 'tracking_mode' => 'serialized', 'unit' => 'unit',
                'asset_prefix' => $prefix, 'replacement_value_kobo' => $naira * 100,
                'low_stock_threshold' => max(1, intdiv($units, 4)),
                'description' => "{$make} {$model}.",
            ]);

            $assets->register($item, [
                'location_id' => $main->id, 'condition' => 'good', 'purchase_date' => '2024-03-01',
                'purchase_cost_kobo' => (int) ($naira * 0.85) * 100, 'supplier' => 'Stagecraft Supplies Ltd',
                'warranty_expires_on' => '2027-03-01', 'notes' => 'Checked and serviced on arrival.',
            ], $units);
        }

        // Spread some realistic states across the fleet (through the services, so the ledger has them).
        auth()->login($admin);
        $all = EquipmentAsset::with('status', 'equipment')->orderBy('id')->get()->keyBy('asset_tag');
        foreach (['ML-005', 'ML-006', 'MW-003', 'S4-010'] as $tag) {
            $assets->move($all[$tag], $secondary, 'Demo: stored at the secondary warehouse');
        }
        $assets->move($all['ML-012'], $maintenanceArea, 'Demo: sent for service');
        $assets->changeStatus($admin, $all['ML-012'], AssetStatus::byCode('under_maintenance'), 'Demo: pan motor noise');
        $assets->recordCondition($all['MW-007'], 'damaged', 'Demo: cracked front lens after load-out');
        $assets->recordCondition($all['HZ-002'], 'requires_inspection', 'Demo: fluid leak reported by crew');
        $assets->changeStatus($admin, $all['COM-010'], AssetStatus::byCode('lost'), 'Demo: not returned from venue');
        $assets->changeStatus($admin, $all['S4-020'], AssetStatus::byCode('retired'), 'Demo: end of life');
        $assets->recordCondition($all['GMA-001'], 'excellent', 'Demo: annual inspection');

        // Quantity-tracked stock.
        $bulk = [
            ['Global Truss F34 3m', 'trussing', 'Global Truss', 'F34 3.0m', 'piece', [[$main, 60], [$secondary, 20]], 380_000, null],
            ['16A Power Cable 10m', 'cables-power', 'Nebo Fabrication', 'H07RN-F 3G2.5', 'piece', [[$main, 200]], 25_000, 40],
            ['DMX 5-pin Cable 10m', 'cables-data-dmx', 'Klotz', 'DMX 5P 10m', 'piece', [[$main, 150]], 18_000, 30],
            ['XLR Microphone Cable 10m', 'cables-audio', 'Van Damme', 'XLR 10m', 'piece', [[$main, 180]], 14_000, 30],
            ['Shackle 3.25T', 'rigging-shackles-slings', 'Crosby', 'G-209 3.25T', 'piece', [[$main, 120]], 9_000, 40],
            ['Universal Road Case', 'road-cases', 'Nebo Fabrication', 'RC-1200', 'case', [[$main, 30]], 260_000, null],
            ['Gaffer Tape 50mm', 'consumables', 'Le Mark', '50mm x 50m black', 'roll', [[$main, 8]], 9_500, 20],
        ];

        foreach ($bulk as $i => [$name, $slug, $make, $model, $unit, $stocks, $naira, $threshold]) {
            $item = $equipment->create([
                'category_id' => $cat($slug), 'name' => $name, 'sku' => sprintf('NS-BLK-%02d', $i + 1),
                'manufacturer' => $make, 'model' => $model, 'tracking_mode' => 'bulk', 'unit' => $unit,
                'replacement_value_kobo' => $naira * 100, 'low_stock_threshold' => $threshold,
                'description' => "{$make} {$model}.",
            ]);

            foreach ($stocks as [$location, $qty]) {
                $stock->receive($item, $location, $qty, true, 'Demo: opening stock');
            }
        }

        $dmx = Equipment::where('sku', 'NS-BLK-03')->first();
        $stock->moveBucket($dmx, $main, StockBucket::Available, StockBucket::Quarantine, 6, 'Demo: failed cable test');
        $stock->transfer(Equipment::where('sku', 'NS-BLK-02')->first(), $main, $secondary, 40, 'Moving cable to the Abuja store for upcoming jobs');

        auth()->logout();
    }
}
