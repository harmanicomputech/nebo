<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One physical unit (ML-001). Status, location and condition are changed only
 * through AssetService so every change lands in the inventory ledger.
 */
#[Fillable(['equipment_id', 'asset_tag', 'serial_number', 'barcode', 'qr_token', 'status_id', 'condition', 'location_id', 'purchase_date', 'purchase_cost_kobo', 'current_value_kobo', 'supplier', 'warranty_expires_on', 'last_inspected_at', 'next_maintenance_due_on', 'usage_count', 'notes'])]
class EquipmentAsset extends Model
{
    use Auditable, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $asset) {
            $asset->qr_token ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_expires_on' => 'date',
            'next_maintenance_due_on' => 'date',
            'last_inspected_at' => 'datetime',
            'purchase_cost_kobo' => 'integer',
            'current_value_kobo' => 'integer',
            'usage_count' => 'integer',
        ];
    }

    public function auditLabel(): string
    {
        return $this->asset_tag;
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    /** @return BelongsTo<AssetStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'status_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    /** @return HasMany<InventoryTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'asset_id');
    }

    public function conditionLabel(): string
    {
        return app(Lookups::class)->label('condition', $this->condition);
    }

    public function conditionBlocksAllocation(): bool
    {
        return (bool) app(Lookups::class)->meta('condition', $this->condition, 'blocks_allocation', false);
    }

    /**
     * Can go to an event right now: allocatable status and a condition that
     * doesn't block allocation. (Date-window holds arrive in Phase 5.)
     */
    public function isAllocatable(): bool
    {
        return $this->status->is_allocatable && ! $this->conditionBlocksAllocation();
    }

    /**
     * @param  Builder<EquipmentAsset>  $query
     * @param  list<string>|null  $blockingConditions
     */
    public function scopeAllocatable(Builder $query, ?array $blockingConditions = null): void
    {
        $blockingConditions ??= app(Lookups::class)->keysWhere('condition', 'blocks_allocation');

        $query->whereHas('status', fn ($s) => $s->where('is_allocatable', true))
            ->when($blockingConditions, fn ($q) => $q->whereNotIn('condition', $blockingConditions));
    }

    /** @param Builder<EquipmentAsset> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';
        $query->where(fn (Builder $q) => $q->where('asset_tag', 'like', $like)
            ->orWhere('serial_number', 'like', $like)
            ->orWhere('barcode', 'like', $like)
            ->orWhereHas('equipment', fn ($e) => $e->where('name', 'like', $like)->orWhere('model', 'like', $like)));
    }
}
