<?php

namespace App\Policies;

use App\Models\EquipmentAsset;
use App\Models\User;

class EquipmentAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, EquipmentAsset $asset): bool
    {
        return $user->can('inventory.view');
    }

    public function update(User $user, EquipmentAsset $asset): bool
    {
        return $user->can('inventory.update') && ! $asset->trashed();
    }

    /** Status, location and condition. Further rules live in AssetService. */
    /** Record an inspection or damage report (inventory staff and technicians). */
    public function inspect(User $user, EquipmentAsset $asset): bool
    {
        return ($user->can('inventory.update') || $user->can('maintenance.manage')) && ! $asset->trashed();
    }

    public function changeState(User $user, EquipmentAsset $asset): bool
    {
        return $user->can('inventory.update') && ! $asset->trashed();
    }

    public function delete(User $user, EquipmentAsset $asset): bool
    {
        return $user->can('inventory.archive') && ! $asset->trashed();
    }

    public function restore(User $user, EquipmentAsset $asset): bool
    {
        return $user->can('inventory.archive') && $asset->trashed();
    }
}
