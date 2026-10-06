<?php

namespace App\Models;

use App\Enums\StockBucket;
use App\Enums\TrackingMode;
use App\Support\Audit\Auditable;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A catalogue item. Serialized items have EquipmentAsset rows; bulk items
 * have StockLevel quantities per location (ARCHITECTURE D6).
 */
#[Fillable(['category_id', 'name', 'sku', 'manufacturer', 'model', 'tracking_mode', 'unit', 'asset_prefix', 'description', 'image_path', 'replacement_value_kobo', 'day_rate_kobo', 'low_stock_threshold', 'is_active'])]
class Equipment extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'equipment';

    protected function casts(): array
    {
        return [
            'tracking_mode' => TrackingMode::class,
            'is_active' => 'boolean',
            'replacement_value_kobo' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    /** @return BelongsTo<EquipmentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'category_id')->withTrashed();
    }

    /** @return HasMany<EquipmentAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(EquipmentAsset::class);
    }

    /** @return HasMany<StockLevel, $this> */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /** @return HasMany<InventoryTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function isSerialized(): bool
    {
        return $this->tracking_mode === TrackingMode::Serialized;
    }

    public function unitLabel(): string
    {
        return app(Lookups::class)->label('unit', $this->unit);
    }

    /**
     * Adds total_units and available_units to each row in one query:
     * serialized → asset counts; bulk → stock quantities.
     *
     * @param  Builder<Equipment>  $query
     */
    public function scopeWithAvailability(Builder $query): void
    {
        $blocking = app(Lookups::class)->keysWhere('condition', 'blocks_allocation');

        $query->withCount([
            'assets as assets_total' => fn ($q) => $q->whereHas('status', fn ($s) => $s->whereNotIn('group', ['lost', 'retired'])),
            'assets as assets_available' => fn ($q) => $q->allocatable($blocking),
        ])->withSum([
            'stockLevels as stock_total' => fn ($q) => $q,
        ], 'quantity')->withSum([
            'stockLevels as stock_available' => fn ($q) => $q->where('bucket', StockBucket::Available->value),
        ], 'quantity');
    }

    /**
     * SQL for "units available now" on the equipment row, for filtering and
     * sorting in the database instead of loading every row.
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function availableUnitsSql(): array
    {
        $blocking = app(Lookups::class)->keysWhere('condition', 'blocks_allocation');
        $notBlocked = $blocking ? ' and ea.condition not in ('.implode(',', array_fill(0, count($blocking), '?')).')' : '';

        $sql = "(case when equipment.tracking_mode = 'serialized' then"
            .' (select count(*) from equipment_assets ea inner join asset_statuses st on st.id = ea.status_id'
            .' where ea.equipment_id = equipment.id and ea.deleted_at is null and st.is_allocatable = ?'.$notBlocked.')'
            ." else (select coalesce(sum(sl.quantity), 0) from stock_levels sl where sl.equipment_id = equipment.id and sl.bucket = 'available') end)";

        return [$sql, array_merge([true], $blocking)];
    }

    /** Requires scopeWithAvailability. */
    public function totalUnits(): int
    {
        return (int) ($this->isSerialized() ? $this->assets_total : $this->stock_total);
    }

    /** Requires scopeWithAvailability. */
    public function availableUnits(): int
    {
        return (int) ($this->isSerialized() ? $this->assets_available : $this->stock_available);
    }

    /** Requires scopeWithAvailability. */
    public function isLowStock(): bool
    {
        return $this->low_stock_threshold !== null && $this->availableUnits() < $this->low_stock_threshold;
    }

    /** @param Builder<Equipment> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';
        $query->where(fn (Builder $q) => $q->where('name', 'like', $like)
            ->orWhere('sku', 'like', $like)
            ->orWhere('manufacturer', 'like', $like)
            ->orWhere('model', 'like', $like)
            ->orWhereHas('assets', fn ($a) => $a->where('asset_tag', 'like', $like)->orWhere('serial_number', 'like', $like)));
    }
}
