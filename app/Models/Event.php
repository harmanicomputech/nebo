<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Models\Concerns\HasNotesAndDocuments;
use App\Models\Concerns\HasStatusHistory;
use App\Support\Audit\Auditable;
use App\Support\Lookups;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A production. Its hold window (setup_starts_at → breakdown_ends_at) is
 * what equipment and crew are reserved against (D8). Status changes only
 * through EventWorkflow.
 */
#[Fillable(['reference', 'event_request_id', 'customer_id', 'name', 'event_type', 'venue', 'venue_meta', 'setup_starts_at', 'starts_at', 'ends_at', 'breakdown_ends_at', 'status', 'project_manager_id', 'production_manager_id', 'budget_kobo', 'production_requirements'])]
class Event extends Model
{
    use Auditable, HasNotesAndDocuments, HasStatusHistory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'setup_starts_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'breakdown_ends_at' => 'datetime',
            'venue_meta' => 'array',
            'budget_kobo' => 'integer',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return BelongsTo<EventRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(EventRequest::class, 'event_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id')->withTrashed();
    }

    /** @return BelongsTo<Staff, $this> */
    public function productionManager(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'production_manager_id')->withTrashed();
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->orderBy('sort_order');
    }

    /** @return HasMany<EventStaff, $this> */
    public function team(): HasMany
    {
        return $this->hasMany(EventStaff::class);
    }

    /** @return HasMany<EquipmentRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(EquipmentRequirement::class);
    }

    /** @return HasMany<LogisticsTrip, $this> */
    public function trips(): HasMany
    {
        return $this->hasMany(LogisticsTrip::class);
    }

    /** @return HasMany<EquipmentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(EquipmentAllocation::class);
    }

    /** @return HasOne<LoadList, $this> */
    public function loadList(): HasOne
    {
        return $this->hasOne(LoadList::class);
    }

    /** @return HasMany<ReturnCheck, $this> */
    public function returnChecks(): HasMany
    {
        return $this->hasMany(ReturnCheck::class)->latest('id');
    }

    public function eventTypeLabel(): string
    {
        return app(Lookups::class)->label('event_type', $this->event_type);
    }

    /** Which part of the production a given day falls in. */
    public function phaseOn(CarbonInterface $day): ?string
    {
        $tz = config('nebo.display_timezone');
        $d = $day->copy()->setTimezone($tz)->startOfDay();

        return match (true) {
            $d->lt($this->setup_starts_at->copy()->setTimezone($tz)->startOfDay()), $d->gt($this->breakdown_ends_at->copy()->setTimezone($tz)->startOfDay()) => null,
            $d->between($this->starts_at->copy()->setTimezone($tz)->startOfDay(), $this->ends_at->copy()->setTimezone($tz)->startOfDay()) => 'show',
            $d->lt($this->starts_at->copy()->setTimezone($tz)->startOfDay()) => 'setup',
            default => 'breakdown',
        };
    }

    /**
     * Events whose hold window overlaps [from, to).
     *
     * @param  Builder<Event>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('setup_starts_at', '<', $to)->where('breakdown_ends_at', '>', $from);
    }

    /** @param Builder<Event> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', EventStatus::activeValues());
    }

    /**
     * Events a user may see with events.view_assigned only: on the team
     * (through their staff profile) or the project manager.
     *
     * @param  Builder<Event>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('events.view')) {
            return;
        }

        $query->where(fn (Builder $q) => $q->where('project_manager_id', $user->id)
            ->orWhereHas('team.staff', fn ($s) => $s->where('user_id', $user->id))
            ->orWhereHas('productionManager', fn ($s) => $s->where('user_id', $user->id)));
    }

    /** @param Builder<Event> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';
        $query->where(fn (Builder $q) => $q->where('reference', 'like', $like)->orWhere('name', 'like', $like)->orWhere('venue', 'like', $like)
            ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('company', 'like', $like)));
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->project_manager_id === $user->id
            || $this->productionManager?->user_id === $user->id
            || $this->team()->whereHas('staff', fn ($s) => $s->where('user_id', $user->id))->exists();
    }

    /** @return HasMany<Quotation, $this> */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->latest('id');
    }
}
