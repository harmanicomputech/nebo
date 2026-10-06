<?php

namespace App\Models;

use App\Models\Concerns\HasNotesAndDocuments;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One entry in an asset's condition history (inspection, return or
 * maintenance). Append-only; photos are documents on the report.
 */
#[Fillable(['asset_id', 'from_condition', 'to_condition', 'source', 'note', 'event_id', 'maintenance_record_id', 'user_id', 'user_name', 'created_at'])]
class ConditionReport extends Model
{
    use HasNotesAndDocuments;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Condition reports are append-only.'));
        static::deleting(fn () => throw new LogicException('Condition reports are append-only.'));
    }

    /** @return BelongsTo<EquipmentAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(EquipmentAsset::class, 'asset_id')->withTrashed();
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    /** @return BelongsTo<MaintenanceRecord, $this> */
    public function maintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    public function conditionLabel(?string $key): string
    {
        return $key ? app(Lookups::class)->label('condition', $key) : '—';
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'return' => 'Check-in',
            'maintenance' => 'Maintenance',
            default => 'Inspection',
        };
    }
}
