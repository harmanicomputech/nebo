<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating and editing roles. Super Administrator is implicit (everything)
 * and its permissions are not edited; system roles cannot be renamed or
 * deleted; nobody can put a permission into a role that they do not hold.
 */
class RoleAdministration
{
    /**
     * @param  array{name: string, description?: ?string}  $data
     * @param  list<string>  $permissions
     */
    public function create(User $actor, array $data, array $permissions): Role
    {
        $permissions = $this->allowedPermissions($actor, $permissions);

        return DB::transaction(function () use ($data, $permissions) {
            $role = Role::create(['name' => $data['name'], 'description' => $data['description'] ?? null, 'guard_name' => 'web', 'is_system' => false]);
            $role->syncPermissions($permissions);
            Audit::record('permissions_changed', "Permissions for role {$role->name} set", $role, null, ['permissions' => $permissions]);

            return $role;
        });
    }

    /**
     * @param  array{name: string, description?: ?string}  $data
     * @param  list<string>  $permissions
     */
    public function update(User $actor, Role $role, array $data, array $permissions): Role
    {
        if ($role->name === PermissionCatalog::SUPER_ADMIN) {
            throw ValidationException::withMessages(['permissions' => 'The Super Administrator role always has every permission and cannot be edited.']);
        }

        if ($role->is_system && $data['name'] !== $role->name) {
            throw ValidationException::withMessages(['name' => 'System roles cannot be renamed.']);
        }

        $before = $role->permissions->pluck('name')->sort()->values()->all();
        $permissions = $this->allowedPermissions($actor, $permissions, $before);

        return DB::transaction(function () use ($role, $data, $permissions, $before) {
            $role->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);

            $after = collect($permissions)->sort()->values()->all();
            if ($before !== $after) {
                $role->syncPermissions($permissions);
                Audit::record('permissions_changed', "Permissions for role {$role->name} changed", $role,
                    ['removed' => array_values(array_diff($before, $after))],
                    ['added' => array_values(array_diff($after, $before))]);
            }

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => 'System roles cannot be deleted.']);
        }

        $count = $role->users()->count();
        if ($count > 0) {
            throw ValidationException::withMessages(['role' => "{$count} user(s) still have this role. Move them to another role first."]);
        }

        $role->delete();
    }

    /**
     * @param  list<string>  $requested
     * @param  list<string>  $existing  permissions already on the role, which may stay
     * @return list<string>
     */
    private function allowedPermissions(User $actor, array $requested, array $existing = []): array
    {
        $requested = array_values(array_unique($requested));
        $unknown = array_diff($requested, PermissionCatalog::all());

        if ($unknown !== []) {
            throw ValidationException::withMessages(['permissions' => 'Unknown permission: '.implode(', ', $unknown)]);
        }

        if (! $actor->isSuperAdmin()) {
            $held = $actor->getAllPermissions()->pluck('name')->all();
            $granted = array_diff($requested, $existing);
            $removed = array_diff($existing, $requested);
            $beyond = array_diff(array_merge($granted, $removed), $held);

            if ($beyond !== []) {
                throw ValidationException::withMessages(['permissions' => 'You can only grant or remove permissions you hold yourself: '.implode(', ', $beyond)]);
            }
        }

        return $requested;
    }
}
