<?php

namespace App\Policies;

use App\Models\LogisticsTrip;
use App\Models\User;

/**
 * logistics.view sees every trip; logistics.view_assigned (drivers, crew)
 * sees and progresses the trips they are on (D57). Planning, editing and
 * cancelling need logistics.manage.
 */
class LogisticsTripPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('logistics.view') || $user->can('logistics.view_assigned');
    }

    public function view(User $user, LogisticsTrip $trip): bool
    {
        return $user->can('logistics.view') || ($user->can('logistics.view_assigned') && $trip->isAssignedTo($user));
    }

    public function create(User $user): bool
    {
        return $user->can('logistics.manage');
    }

    public function update(User $user, LogisticsTrip $trip): bool
    {
        return $user->can('logistics.manage') && $trip->status->isActive() && $trip->status->value !== 'in_transit';
    }

    public function cancel(User $user, LogisticsTrip $trip): bool
    {
        return $user->can('logistics.manage') && $trip->status->isActive();
    }

    /** Loading, departing and arriving: managers, or the driver and crew on the trip. */
    public function progress(User $user, LogisticsTrip $trip): bool
    {
        return $trip->status->isActive() && ($user->can('logistics.manage') || ($user->can('logistics.view_assigned') && $trip->isAssignedTo($user)));
    }

    public function addNote(User $user, LogisticsTrip $trip): bool
    {
        return $user->can('logistics.manage') || ($user->can('logistics.view_assigned') && $trip->isAssignedTo($user));
    }
}
