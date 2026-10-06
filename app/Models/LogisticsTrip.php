<?php

namespace App\Models;

use App\Enums\TripDirection;
use App\Enums\TripStatus;
use App\Models\Concerns\HasNotesAndDocuments;
use App\Models\Concerns\HasStatusHistory;
use App\Support\Audit\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A trip. Created and changed only through TripService. */
#[Fillable(['reference', 'event_id', 'direction', 'vehicle_id', 'driver_id', 'origin', 'destination', 'departs_at', 'arrives_at', 'status', 'departed_at', 'arrived_at', 'received_by', 'instructions', 'created_by'])]
class LogisticsTrip extends Model
{
    use Auditable, HasNotesAndDocuments, HasStatusHistory;

    protected function casts(): array
    {
        return [
            'direction' => TripDirection::class,
            'status' => TripStatus::class,
            'departs_at' => 'datetime',
            'arrives_at' => 'datetime',
            'departed_at' => 'datetime',
            'arrived_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /** @return BelongsTo<Staff, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'driver_id')->withTrashed();
    }

    /** @return BelongsToMany<Staff, $this> */
    public function crew(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'logistics_trip_crew', 'trip_id', 'staff_id')->withTimestamps()->withTrashed();
    }

    /** @return BelongsToMany<EquipmentAllocation, $this> */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(EquipmentAllocation::class, 'logistics_trip_items', 'trip_id', 'allocation_id')->withTimestamps();
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', TripStatus::activeValues());
    }

    /** Active trips whose scheduled window overlaps [from, to). */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->active()->where('departs_at', '<', $to)->where('arrives_at', '>', $from);
    }

    /** Drivers and trip crew with logistics.view_assigned see only their own trips (D57). */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('logistics.view')) {
            return;
        }

        $query->where(fn ($q) => $q->whereHas('driver', fn ($d) => $d->where('user_id', $user->id))
            ->orWhereHas('crew', fn ($c) => $c->where('user_id', $user->id)));
    }

    public function isAssignedTo(User $user): bool
    {
        return self::query()->whereKey($this->id)
            ->where(fn ($q) => $q->whereHas('driver', fn ($d) => $d->where('user_id', $user->id))
                ->orWhereHas('crew', fn ($c) => $c->where('user_id', $user->id)))
            ->exists();
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $query->where(fn ($q) => $q->where('reference', 'like', $like)->orWhere('origin', 'like', $like)->orWhere('destination', 'like', $like)
            ->orWhereHas('event', fn ($e) => $e->where('name', 'like', $like)->orWhere('reference', 'like', $like))
            ->orWhereHas('vehicle', fn ($v) => $v->where('registration', 'like', $like)->orWhere('name', 'like', $like)));
    }
}
