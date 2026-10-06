<?php

namespace App\Models;

use App\Enums\InventoryTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One inventory movement. Append-only, like the audit log.
 */
#[Fillable(['type', 'equipment_id', 'asset_id', 'quantity', 'from_location_id', 'to_location_id', 'from_status_id', 'to_status_id', 'from_condition', 'to_condition', 'from_bucket', 'to_bucket', 'event_id', 'user_id', 'user_name', 'note', 'occurred_at', 'created_at'])]
class InventoryTransaction extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Inventory ledger entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Inventory ledger entries cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'type' => InventoryTransactionType::class,
            'occurred_at' => 'datetime',
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    /** @return BelongsTo<EquipmentAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(EquipmentAsset::class, 'asset_id')->withTrashed();
    }

    /** @return BelongsTo<Location, $this> */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id')->withTrashed();
    }

    /** @return BelongsTo<Location, $this> */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id')->withTrashed();
    }

    /** @return BelongsTo<AssetStatus, $this> */
    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'from_status_id');
    }

    /** @return BelongsTo<AssetStatus, $this> */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'to_status_id');
    }
}
