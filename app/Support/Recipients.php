<?php

namespace App\Support;

use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Support\Collection;

/** Active users who hold a permission (super administrators included). */
class Recipients
{
    /**
     * @return Collection<int, User>
     */
    public static function withPermission(string $permission): Collection
    {
        return User::query()->active()
            ->where(fn ($q) => $q->permission($permission)->orWhereHas('roles', fn ($r) => $r->where('name', PermissionCatalog::SUPER_ADMIN)))
            ->get();
    }
}
