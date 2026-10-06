<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * events.view sees all events; events.view_assigned (crew, technicians)
 * sees only events they are on. Closed events are read-only except notes.
 */
class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('events.view') || $user->can('events.view_assigned');
    }

    public function view(User $user, Event $event): bool
    {
        return $user->can('events.view') || ($user->can('events.view_assigned') && $event->isAssignedTo($user));
    }

    public function create(User $user): bool
    {
        return $user->can('events.create');
    }

    public function update(User $user, Event $event): bool
    {
        return $user->can('events.update') && ! $event->status->isClosed() && ! $event->trashed();
    }

    public function changeStatus(User $user, Event $event): bool
    {
        return $user->can('events.status') && ! $event->status->isClosed() && ! $event->trashed();
    }

    public function manageTeam(User $user, Event $event): bool
    {
        return $user->can('events.team') && ! $event->status->isClosed() && ! $event->trashed();
    }

    public function addNote(User $user, Event $event): bool
    {
        return ($user->can('events.update') || $user->can('events.team')) && $this->view($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->can('events.archive') && ! $event->trashed();
    }

    public function restore(User $user, Event $event): bool
    {
        return $user->can('events.archive') && $event->trashed();
    }
}
