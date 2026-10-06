<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventStaff;
use App\Models\Staff;
use App\Notifications\AssignedToEvent;
use App\Support\Audit\Audit;
use App\Support\Format;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Event crew. Staff can't be double-booked across overlapping hold windows
 * unless a manager overrides it with a reason (recorded in the audit log).
 */
class TeamService
{
    /**
     * Other active events this person is on whose window overlaps.
     *
     * @return Collection<int, Event>
     */
    public function conflicts(Staff $staff, Event $event): Collection
    {
        return Event::query()->active()->whereKeyNot($event->id)
            ->overlapping($event->setup_starts_at, $event->breakdown_ends_at)
            ->where(fn ($q) => $q->whereHas('team', fn ($t) => $t->where('staff_id', $staff->id))->orWhere('production_manager_id', $staff->id))
            ->orderBy('setup_starts_at')->get();
    }

    public function assign(Event $event, Staff $staff, string $role, ?string $notes = null, ?string $overrideReason = null): EventStaff
    {
        if (! $staff->is_active || $staff->trashed()) {
            throw ValidationException::withMessages(['staff_id' => "{$staff->name} is not active."]);
        }

        if ($event->team()->where('staff_id', $staff->id)->exists()) {
            throw ValidationException::withMessages(['staff_id' => "{$staff->name} is already on this event."]);
        }

        $conflicts = $this->conflicts($staff, $event);

        if ($conflicts->isNotEmpty() && blank($overrideReason)) {
            $first = $conflicts->first();
            throw ValidationException::withMessages(['staff_id' => "{$staff->name} is already booked on {$first->name} ({$first->reference}, ".Format::date($first->setup_starts_at).' – '.Format::date($first->breakdown_ends_at).'). Give an override reason to book them anyway.']);
        }

        $member = $event->team()->create(['staff_id' => $staff->id, 'role' => $role, 'notes' => $notes]);

        Audit::record('team_assigned', "{$staff->name} added to {$event->reference} as {$member->roleLabel()}".($conflicts->isNotEmpty() ? " despite overlapping with {$conflicts->pluck('reference')->implode(', ')}: {$overrideReason}" : ''), $event);

        if ($staff->loadMissing('user')->user && $staff->user->is_active) {
            $staff->user->notify(new AssignedToEvent($event, $member->roleLabel()));
        }

        return $member;
    }

    public function remove(Event $event, EventStaff $member): void
    {
        abort_unless($member->event_id === $event->id, 404);
        $name = $member->staff->name;
        $member->delete();
        Audit::record('team_removed', "{$name} removed from {$event->reference}", $event);
    }
}
