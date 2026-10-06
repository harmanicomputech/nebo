<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountRolesChanged;
use App\Notifications\WelcomeToNebo;
use App\Support\Audit\Audit;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating and changing login accounts, with the rules that keep the system
 * administrable: nobody can lock themselves out, the last super administrator
 * cannot be removed, and nobody can grant access they do not have.
 */
class UserAdministration
{
    /**
     * Roles the actor may hand out: Super Administrator only by a super
     * administrator; any other role only if the actor holds all its permissions.
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(User $actor): Collection
    {
        $roles = Role::query()->with('permissions')->orderByDesc('is_system')->orderBy('name')->get();

        if ($actor->isSuperAdmin()) {
            return $roles;
        }

        $held = $actor->getAllPermissions()->pluck('name');

        return $roles->filter(fn (Role $role) => $role->name !== PermissionCatalog::SUPER_ADMIN
            && $role->permissions->pluck('name')->diff($held)->isEmpty())->values();
    }

    /**
     * @param  array{name: string, email: string, phone?: ?string, job_title?: ?string, password: string}  $data
     * @param  list<int|string>  $roleIds
     */
    public function create(User $actor, array $data, array $roleIds): User
    {
        $roles = $this->resolveRoles($actor, $roleIds);

        return DB::transaction(function () use ($data, $roles) {
            $user = User::create($data + ['is_active' => true]);
            $user->syncRoles($roles);

            Audit::record('roles_assigned', "Roles for {$user->name} set to: ".$this->names($roles), $user, null, ['roles' => $roles->pluck('name')->all()]);
            $user->notify(new WelcomeToNebo);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, phone?: ?string, job_title?: ?string}  $data
     * @param  list<int|string>|null  $roleIds  null leaves roles unchanged
     */
    public function update(User $actor, User $user, array $data, ?array $roleIds): User
    {
        return DB::transaction(function () use ($actor, $user, $data, $roleIds) {
            $user->update($data);

            if ($roleIds !== null) {
                $roles = $this->resolveRoles($actor, $roleIds, $user);
                $before = $user->getRoleNames()->sort()->values()->all();
                $after = $roles->pluck('name')->sort()->values()->all();

                if ($before !== $after) {
                    $this->guardSuperAdminRemoval($user, in_array(PermissionCatalog::SUPER_ADMIN, $after, true));

                    if ($actor->is($user) && ! $actor->isSuperAdmin() && array_diff($before, $after) !== []) {
                        throw ValidationException::withMessages(['roles' => 'You cannot remove your own roles. Ask another administrator.']);
                    }

                    $user->syncRoles($roles);
                    Audit::record('roles_changed', "Roles for {$user->name} changed", $user, ['roles' => $before], ['roles' => $after]);
                    $user->notify(new AccountRolesChanged($after, $actor->name));
                }
            }

            return $user;
        });
    }

    public function setActive(User $actor, User $user, bool $active): void
    {
        if ($actor->is($user) && ! $active) {
            throw ValidationException::withMessages(['user' => 'You cannot deactivate your own account.']);
        }

        if (! $actor->isSuperAdmin() && $user->isSuperAdmin()) {
            throw ValidationException::withMessages(['user' => 'Only a super administrator can change another super administrator.']);
        }

        if (! $active && $user->isSuperAdmin()) {
            $this->guardSuperAdminRemoval($user, false);
        }

        $user->update(['is_active' => $active]);
    }

    public function resetPassword(User $actor, User $user, string $password): void
    {
        if (! $actor->isSuperAdmin() && $user->isSuperAdmin()) {
            throw ValidationException::withMessages(['password' => 'Only a super administrator can reset a super administrator\'s password.']);
        }

        $user->forceFill(['password' => $password, 'remember_token' => null])->save();
        Audit::record('password_reset', "Password for {$user->name} reset by an administrator", $user);
    }

    /**
     * @param  list<int|string>  $roleIds
     * @return Collection<int, Role>
     */
    private function resolveRoles(User $actor, array $roleIds, ?User $target = null): Collection
    {
        $roleIds = array_map('intval', $roleIds);
        $allowed = $this->assignableRoles($actor)->keyBy('id');
        $requested = Role::query()->whereIn('id', $roleIds)->get();

        // Roles the target already has may be kept even if the actor could not grant them.
        $kept = $target ? $target->roles->pluck('id')->all() : [];

        foreach ($requested as $role) {
            if (! $allowed->has($role->id) && ! in_array($role->id, $kept, true)) {
                throw ValidationException::withMessages(['roles' => "You cannot assign the {$role->name} role."]);
            }
        }

        if ($target && ! $actor->isSuperAdmin()) {
            // Removing a role the actor could not have granted is equally out of bounds.
            foreach ($target->roles as $role) {
                if (! in_array($role->id, $roleIds, true) && ! $allowed->has($role->id)) {
                    throw ValidationException::withMessages(['roles' => "You cannot remove the {$role->name} role."]);
                }
            }
        }

        return $requested;
    }

    private function guardSuperAdminRemoval(User $user, bool $keepsSuperAdmin): void
    {
        if ($keepsSuperAdmin || ! $user->isSuperAdmin()) {
            return;
        }

        $others = User::role(PermissionCatalog::SUPER_ADMIN)->active()->whereKeyNot($user->getKey())->count();

        if ($others === 0) {
            throw ValidationException::withMessages(['roles' => 'This is the last active super administrator. Make someone else a super administrator first.']);
        }
    }

    /**
     * @param  Collection<int, Role>  $roles
     */
    private function names(Collection $roles): string
    {
        return $roles->isEmpty() ? 'none' : $roles->pluck('name')->implode(', ');
    }
}
