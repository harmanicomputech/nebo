<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Recurring maintenance for one unit. Changed through MaintenanceScheduler. */
#[Fillable(['asset_id', 'type', 'interval_days', 'next_due_on', 'last_done_on', 'last_reminded_on', 'notes', 'is_active', 'created_by'])]
class MaintenanceSchedule extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'interval_days' => 'integer',
            'next_due_on' => 'date',
            'last_done_on' => 'date',
            'last_reminded_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<EquipmentAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(EquipmentAsset::class, 'asset_id')->withTrashed();
    }

    /** @return HasMany<MaintenanceRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class, 'schedule_id');
    }

    public function typeLabel(): string
    {
        return app(Lookups::class)->label('maintenance_type', $this->type);
    }

    /** Active schedules due on or before the given date (Lagos). */
    public function scopeDueBy(Builder $query, string $date): void
    {
        $query->where('is_active', true)->whereDate('next_due_on', '<=', $date)
            ->whereHas('asset', fn ($q) => $q->whereNull('deleted_at')->whereHas('status', fn ($s) => $s->whereNotIn('group', ['retired', 'lost'])));
    }

    public function isOverdue(): bool
    {
        return $this->next_due_on->lt(now(config('nebo.display_timezone'))->startOfDay());
    }
}
