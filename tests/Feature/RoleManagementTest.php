<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    public function test_seeder_creates_the_roles_from_the_brief(): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys(PermissionCatalog::defaultRoles()),
            Role::pluck('name')->all(),
        );
        $this->assertTrue(Role::where('name', 'Viewer')->first()->hasPermissionTo('inventory.view'));
        $this->assertFalse(Role::where('name', 'Viewer')->first()->hasPermissionTo('inventory.update'));
    }

    public function test_seeder_is_idempotent_and_keeps_admin_edits(): void
    {
        $viewer = Role::where('name', 'Viewer')->first();
        $viewer->revokePermissionTo('inventory.view');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(count(PermissionCatalog::defaultRoles()), Role::count());
        $this->assertFalse($viewer->fresh()->hasPermissionTo('inventory.view'));
    }

    public function test_admin_creates_a_custom_role(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/app/roles', [
            'name' => 'Warehouse Assistant',
            'description' => 'Checks equipment in and out.',
            'permissions' => ['inventory.view', 'returns.manage'],
        ])->assertRedirect();

        $role = Role::where('name', 'Warehouse Assistant')->firstOrFail();
        $this->assertFalse($role->is_system);
        $this->assertEqualsCanonicalizing(['inventory.view', 'returns.manage'], $role->permissions->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['event' => 'permissions_changed', 'auditable_type' => 'Role']);
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $this->actingAs($this->superAdmin())->post('/app/roles', ['name' => 'Bad', 'permissions' => ['everything.ever']])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_permission_changes_are_audited_with_before_and_after(): void
    {
        $admin = $this->superAdmin();
        $role = Role::where('name', 'Crew')->first();

        $this->actingAs($admin)->put("/app/roles/{$role->id}", [
            'name' => 'Crew', 'description' => $role->description, 'permissions' => ['dashboard.view', 'events.view_assigned', 'documents.view', 'inventory.view'],
        ])->assertSessionHasNoErrors();

        $log = AuditLog::where('event', 'permissions_changed')->latest('id')->firstOrFail();
        $this->assertSame(['inventory.view'], $log->new_values['added']);
        $this->assertSame(['loadlists.manage'], $log->old_values['removed']);
    }

    public function test_system_roles_cannot_be_renamed_or_deleted(): void
    {
        $admin = $this->superAdmin();
        $role = Role::where('name', 'Viewer')->first();

        $this->actingAs($admin)->put("/app/roles/{$role->id}", ['name' => 'Guest', 'permissions' => []])->assertSessionHasErrors('name');
        $this->actingAs($admin)->delete("/app/roles/{$role->id}")->assertForbidden();
        $this->assertDatabaseHas('roles', ['name' => 'Viewer']);
    }

    public function test_super_admin_role_permissions_cannot_be_edited(): void
    {
        $admin = $this->superAdmin();
        $role = Role::where('name', PermissionCatalog::SUPER_ADMIN)->first();

        $this->actingAs($admin)->put("/app/roles/{$role->id}", ['name' => $role->name, 'permissions' => ['dashboard.view']])
            ->assertSessionHasErrors('permissions');
    }

    public function test_custom_role_in_use_cannot_be_deleted(): void
    {
        $admin = $this->superAdmin();
        $role = Role::create(['name' => 'Temp', 'guard_name' => 'web']);
        User::factory()->create()->assignRole($role);

        $this->actingAs($admin)->delete("/app/roles/{$role->id}")->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['name' => 'Temp']);

        $role->users()->detach();
        $this->actingAs($admin)->delete("/app/roles/{$role->id}")->assertRedirect(route('app.roles.index'));
        $this->assertDatabaseMissing('roles', ['name' => 'Temp']);
    }

    public function test_role_manager_cannot_grant_permissions_they_do_not_hold(): void
    {
        $role = Role::create(['name' => 'Access Admin', 'guard_name' => 'web']);
        $role->syncPermissions(['roles.view', 'roles.manage', 'inventory.view']);
        $manager = User::factory()->create();
        $manager->assignRole($role);

        // Granting themselves (via their own role) something they lack.
        $this->actingAs($manager)->put("/app/roles/{$role->id}", [
            'name' => 'Access Admin', 'permissions' => ['roles.view', 'roles.manage', 'inventory.view', 'users.create'],
        ])->assertSessionHasErrors('permissions');

        $this->actingAs($manager)->post('/app/roles', ['name' => 'Escalate', 'permissions' => ['settings.manage']])
            ->assertSessionHasErrors('permissions');

        $this->assertFalse($manager->fresh()->can('users.create'));
    }
}
