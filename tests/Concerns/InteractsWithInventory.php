<?php

namespace Tests\Concerns;

use App\Models\Equipment;
use App\Models\EquipmentAsset;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Services\Inventory\AssetService;
use App\Services\Inventory\EquipmentService;
use App\Services\Inventory\StockService;
use Illuminate\Support\Collection;

/**
 * Builds inventory through the real services, so tests exercise the same
 * code paths (and ledger writes) as the application.
 */
trait InteractsWithInventory
{
    protected function category(string $slug = 'moving-lights'): EquipmentCategory
    {
        return EquipmentCategory::where('slug', $slug)->firstOrFail();
    }

    protected function location(string $code = 'MAIN'): Location
    {
        return Location::where('code', $code)->firstOrFail();
    }

    protected function serializedItem(array $attributes = []): Equipment
    {
        return app(EquipmentService::class)->create(array_merge([
            'category_id' => $this->category()->id,
            'name' => 'Test Moving Light',
            'sku' => 'TEST-ML-'.uniqid(),
            'manufacturer' => 'Robe',
            'model' => 'MegaPointe',
            'tracking_mode' => 'serialized',
            'unit' => 'unit',
            'asset_prefix' => 'TML',
        ], $attributes));
    }

    protected function bulkItem(array $attributes = []): Equipment
    {
        return app(EquipmentService::class)->create(array_merge([
            'category_id' => $this->category('cables')->id,
            'name' => 'Test Cable 10m',
            'sku' => 'TEST-CBL-'.uniqid(),
            'tracking_mode' => 'bulk',
            'unit' => 'piece',
        ], $attributes));
    }

    /**
     * @return Collection<int, EquipmentAsset>
     */
    protected function addUnits(Equipment $equipment, int $count = 1, array $data = []): Collection
    {
        return app(AssetService::class)->register($equipment, array_merge([
            'location_id' => $this->location()->id,
            'condition' => 'good',
        ], $data), $count);
    }

    protected function receive(Equipment $equipment, int $quantity, string $location = 'MAIN'): void
    {
        app(StockService::class)->receive($equipment, $this->location($location), $quantity);
    }
}
