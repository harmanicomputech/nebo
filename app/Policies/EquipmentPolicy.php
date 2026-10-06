<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;

class EquipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, Equipment $equipment): bool
    {
        return $user->can('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.create');
    }

    public function update(User $user, Equipment $equipment): bool
    {
        return $user->can('inventory.update') && ! $equipment->trashed();
    }

    /** Receive, transfer, quarantine, write off and count bulk stock. */
    public function adjustStock(User $user, Equipment $equipment): bool
    {
        return $user->can('inventory.adjust') && ! $equipment->isSerialized() && ! $equipment->trashed();
    }

    public function addAssets(User $user, Equipment $equipment): bool
    {
        return $user->can('inventory.create') && $equipment->isSerialized() && ! $equipment->trashed();
    }

    public function delete(User $user, Equipment $equipment): bool
    {
        return $user->can('inventory.archive') && ! $equipment->trashed();
    }

    public function restore(User $user, Equipment $equipment): bool
    {
        return $user->can('inventory.archive') && $equipment->trashed();
    }
}
