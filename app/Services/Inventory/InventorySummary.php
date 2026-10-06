<?php

namespace App\Services\Inventory;

use App\Enums\AssetStatusGroup;
use App\Enums\StockBucket;
use App\Models\Equipment;
use App\Models\EquipmentAsset;
use App\Models\StockLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Inventory figures for the dashboard and (later) reports. Everything is
 * aggregated in SQL; nothing loads the full fleet.
 */
class InventorySummary
{
    /**
     * Serialized units per status group (archived assets excluded).
     *
     * @return array<string, int> keyed by AssetStatusGroup value
     */
    public function assetsByGroup(): array
    {
        $counts = EquipmentAsset::query()
            ->join('asset_statuses', 'asset_statuses.id', '=', 'equipment_assets.status_id')
            ->groupBy('asset_statuses.group')
            ->select('asset_statuses.group', DB::raw('count(*) as total'))
            ->pluck('total', 'group');

        return collect(AssetStatusGroup::cases())->mapWithKeys(fn ($g) => [$g->value => (int) ($counts[$g->value] ?? 0)])->all();
    }

    public function allocatableAssets(): int
    {
        return EquipmentAsset::query()->allocatable()->count();
    }

    /**
     * @return array{available: int, quarantine: int}
     */
    public function bulkStock(): array
    {
        $sums = StockLevel::query()->whereHas('equipment')->groupBy('bucket')->select('bucket', DB::raw('sum(quantity) as total'))->pluck('total', 'bucket');

        return [
            'available' => (int) ($sums[StockBucket::Available->value] ?? 0),
            'quarantine' => (int) ($sums[StockBucket::Quarantine->value] ?? 0),
        ];
    }

    /**
     * @return Collection<int, Equipment>
     */
    public function lowStock(int $limit = 6): Collection
    {
        [$sql, $bindings] = Equipment::availableUnitsSql();

        return Equipment::query()->withAvailability()
            ->whereNotNull('low_stock_threshold')
            ->whereRaw("$sql < equipment.low_stock_threshold", $bindings)
            ->orderByRaw("$sql asc", $bindings)
            ->limit($limit)->get();
    }

    public function lowStockCount(): int
    {
        [$sql, $bindings] = Equipment::availableUnitsSql();

        return Equipment::query()->whereNotNull('low_stock_threshold')->whereRaw("$sql < equipment.low_stock_threshold", $bindings)->count();
    }

    /** Units whose next maintenance date is within $days (or overdue). */
    public function maintenanceDue(int $days = 14): int
    {
        return EquipmentAsset::query()->whereNotNull('next_maintenance_due_on')
            ->whereDate('next_maintenance_due_on', '<=', now(config('nebo.display_timezone'))->addDays($days)->toDateString())
            ->whereHas('status', fn ($q) => $q->whereNotIn('group', [AssetStatusGroup::Retired->value, AssetStatusGroup::Lost->value]))
            ->count();
    }
}
