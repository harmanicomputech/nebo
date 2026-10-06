<?php

namespace App\Policies;

use App\Models\EventRequest;
use App\Models\User;

class EventRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('requests.view');
    }

    public function view(User $user, EventRequest $request): bool
    {
        return $user->can('requests.view');
    }

    /** Assign, add notes, upload files. */
    public function update(User $user, EventRequest $request): bool
    {
        return $user->can('requests.manage');
    }

    public function changeStatus(User $user, EventRequest $request): bool
    {
        return $user->can('requests.status') && $request->status->isOpen();
    }
}
