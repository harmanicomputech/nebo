<?php

namespace Tests\Feature;

use App\Enums\StockBucket;
use App\Models\AssetStatus;
use App\Models\Equipment;
use App\Models\EquipmentAsset;
use App\Models\Location;
use App\Services\Inventory\AssetService;
use App\Services\Inventory\InitialInventory;
use App\Services\Inventory\StockService;
use App\Support\SampleData;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InitialInventoryTest extends TestCase
{
    private function stock(string $sku): int
    {
        return (int) DB::table('stock_levels')->join('equipment', 'equipment.id', '=', 'stock_levels.equipment_id')->where('sku', $sku)->sum('quantity');
    }

    public function test_the_stage_and_screen_inventory_is_imported_once(): void
    {
        $this->assertSame(27, app(InitialInventory::class)->import());
        $this->assertSame(0, app(InitialInventory::class)->import(), 'second run adds nothing');

        $this->assertSame(27, Equipment::count());
        $this->assertSame(48, EquipmentAsset::whereHas('equipment', fn ($q) => $q->where('sku', 'LED-P391-S'))->count());
        $this->assertSame(24, EquipmentAsset::whereHas('equipment', fn ($q) => $q->where('sku', 'LED-P391-L'))->count());
        $this->assertSame(3, EquipmentAsset::whereHas('equipment', fn ($q) => $q->where('sku', 'VID-VX600'))->count());
        $this->assertSame(6, EquipmentAsset::whereHas('equipment', fn ($q) => $q->where('sku', 'RIG-HOIST'))->count());
        $this->assertSame(900, $this->stock('TRS-PIN'));
        $this->assertSame(21, $this->stock('TRS-44-2M'));
        $this->assertSame(50, $this->stock('STG-PANEL'));
        $this->assertTrue(EquipmentAsset::where('asset_tag', 'LP5-001')->exists());
        $this->assertFalse(SampleData::exists(), 'real inventory is not sample data');
    }

    public function test_applying_an_update_adds_the_inventory(): void
    {
        $this->actingAs($this->superAdmin())->post(route('app.settings.system.update'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '27 inventory items were added'));
        $this->assertSame(27, Equipment::count());
    }

    public function test_clearing_sample_data_puts_the_real_inventory_back(): void
    {
        $owner = $this->superAdmin();
        app(InitialInventory::class)->import();
        $panel = EquipmentAsset::where('asset_tag', 'LP5-001')->firstOrFail();
        $pins = Equipment::where('sku', 'TRS-PIN')->firstOrFail();
        $main = Location::where('code', 'MAIN')->firstOrFail();

        SampleData::record(function () use ($owner, $panel, $pins, $main) {
            $this->actingAs($owner);
            app(AssetService::class)->changeStatus($owner, $panel, AssetStatus::byCode('damaged'), 'Cracked');
            app(StockService::class)->writeOff($pins, $main, StockBucket::Available, 6, 'Lost at a venue');
            Equipment::whereKey($pins->id)->update(['day_rate_kobo' => 10000]);
        });
        $this->assertSame('damaged', $panel->fresh()->status->code);
        $this->assertSame(894, $this->stock('TRS-PIN'));

        $this->actingAs($owner)->delete(route('app.settings.system.sample.clear'))->assertSessionHas('success');

        $this->assertSame('available', $panel->fresh()->status->code);
        $this->assertSame(900, $this->stock('TRS-PIN'));
        $this->assertNull($pins->fresh()->day_rate_kobo);
        $this->assertSame(0, DB::table('sample_snapshots')->count());
    }
}
