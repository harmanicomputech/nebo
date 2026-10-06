<?php

namespace App\Models;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\Concerns\HasNotesAndDocuments;
use App\Models\Concerns\HasStatusHistory;
use App\Support\Audit\Auditable;
use App\Support\Lookups;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A maintenance job. Created and changed only through MaintenanceService. */
#[Fillable(['reference', 'asset_id', 'equipment_id', 'schedule_id', 'event_id', 'type', 'priority', 'status', 'source', 'issue', 'description', 'reported_by', 'technician_id', 'scheduled_starts_at', 'scheduled_ends_at', 'started_at', 'completed_at', 'cost_kobo', 'parts_used', 'work_done', 'outcome_condition'])]
class MaintenanceRecord extends Model
{
    use Auditable, HasNotesAndDocuments, HasStatusHistory;

    protected function casts(): array
    {
        return [
            'status' => MaintenanceStatus::class,
            'priority' => MaintenancePriority::class,
            'scheduled_starts_at' => 'datetime',
            'scheduled_ends_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cost_kobo' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /** @return BelongsTo<EquipmentAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(EquipmentAsset::class, 'asset_id')->withTrashed();
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    /** @return BelongsTo<MaintenanceSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'schedule_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by')->withTrashed();
    }

    /** @return BelongsTo<Staff, $this> */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'technician_id')->withTrashed();
    }

    public function typeLabel(): string
    {
        return app(Lookups::class)->label('maintenance_type', $this->type);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', MaintenanceStatus::openValues());
    }

    /** Open jobs whose scheduled window overlaps [from, to). */
    public function scopeWindowOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->open()->whereNotNull('scheduled_starts_at')->whereNotNull('scheduled_ends_at')
            ->where('scheduled_starts_at', '<', $to)->where('scheduled_ends_at', '>', $from);
    }

    /** Technicians with maintenance.view_assigned see only their own jobs. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('maintenance.view')) {
            return;
        }

        $query->whereHas('technician', fn ($q) => $q->where('user_id', $user->id));
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->technician_id !== null && Staff::whereKey($this->technician_id)->where('user_id', $user->id)->exists();
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $query->where(fn ($q) => $q->where('reference', 'like', $like)->orWhere('issue', 'like', $like)
            ->orWhereHas('asset', fn ($a) => $a->withTrashed()->where('asset_tag', 'like', $like)->orWhere('serial_number', 'like', $like))
            ->orWhereHas('equipment', fn ($e) => $e->withTrashed()->where('name', 'like', $like)));
    }
}
