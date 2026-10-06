<?php

namespace App\Policies;

use App\Models\ProductionPackage;
use App\Models\User;

class ProductionPackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('packages.manage') || $user->can('quotations.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('packages.manage');
    }

    public function update(User $user, ProductionPackage $package): bool
    {
        return $user->can('packages.manage');
    }

    public function delete(User $user, ProductionPackage $package): bool
    {
        return $user->can('packages.manage');
    }
}
