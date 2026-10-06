<?php

namespace App\Policies;

use App\Models\MaintenanceRecord;
use App\Models\User;

/**
 * maintenance.view sees every job; maintenance.view_assigned (technicians)
 * sees jobs assigned to their staff profile. maintenance.manage works on the
 * jobs the user can see. Closed jobs are read-only except notes.
 */
class MaintenanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('maintenance.view') || $user->can('maintenance.view_assigned');
    }

    public function view(User $user, MaintenanceRecord $record): bool
    {
        return $user->can('maintenance.view') || ($user->can('maintenance.view_assigned') && $record->isAssignedTo($user));
    }

    public function create(User $user): bool
    {
        return $user->can('maintenance.manage');
    }

    public function update(User $user, MaintenanceRecord $record): bool
    {
        return $user->can('maintenance.manage') && $this->view($user, $record) && $record->status->isOpen();
    }

    public function addNote(User $user, MaintenanceRecord $record): bool
    {
        return $user->can('maintenance.manage') && $this->view($user, $record);
    }
}
