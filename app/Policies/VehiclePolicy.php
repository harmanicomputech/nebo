<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('logistics.view');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->can('logistics.view');
    }

    public function create(User $user): bool
    {
        return $user->can('vehicles.manage');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.manage') && ! $vehicle->trashed();
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.manage');
    }
}
