<?php

namespace App\Models;

use App\Enums\AllocationState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Equipment held by an event for its hold window. Created and changed only
 * through AllocationService, LoadListService and ReturnService.
 */
#[Fillable(['event_id', 'equipment_id', 'asset_id', 'location_id', 'quantity', 'hold_starts_at', 'hold_ends_at', 'state', 'allocated_by', 'checked_out_at', 'returned_at', 'released_at', 'return_outcome'])]
class EquipmentAllocation extends Model
{
    protected function casts(): array
    {
        return [
            'state' => AllocationState::class,
            'quantity' => 'integer',
            'hold_starts_at' => 'datetime',
            'hold_ends_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'returned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
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
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    /** @return HasOne<LoadListItem, $this> */
    public function loadListItem(): HasOne
    {
        return $this->hasOne(LoadListItem::class, 'allocation_id');
    }

    /** @param Builder<EquipmentAllocation> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('state', AllocationState::activeValues());
    }

    /**
     * Active allocations whose window overlaps [from, to).
     *
     * @param  Builder<EquipmentAllocation>  $query
     */
    public function scopeOverlapping(Builder $query, $from, $to): void
    {
        $query->active()->where('hold_starts_at', '<', $to)->where('hold_ends_at', '>', $from);
    }
}
