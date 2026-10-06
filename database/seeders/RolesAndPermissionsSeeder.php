<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: safe to run on every deploy. Adds new permissions from the
 * catalog, removes ones the code no longer checks, and creates missing
 * default roles. Existing roles keep the permissions administrators gave them.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $catalog = PermissionCatalog::all();
        foreach ($catalog as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Permission::query()->where('guard_name', 'web')->whereNotIn('name', $catalog)->delete();

        foreach (PermissionCatalog::defaultRoles() as $name => $definition) {
            $role = Role::query()->where(['name' => $name, 'guard_name' => 'web'])->first();

            if ($role) {
                $role->update(['is_system' => true]);

                continue;
            }

            $role = Role::create(['name' => $name, 'guard_name' => 'web', 'description' => $definition['description'], 'is_system' => true]);

            if ($definition['permissions'] !== '*') {
                $role->syncPermissions($definition['permissions']);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
