<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountRolesChanged;
use App\Notifications\WelcomeToNebo;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private function roleId(string $name): int
    {
        return Role::where('name', $name)->value('id');
    }

    public function test_admin_creates_a_user_with_roles(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/app/users', [
            'name' => 'Kemi Lawal',
            'email' => 'KEMI@example.com',
            'phone' => '+234 803 123 4567',
            'job_title' => 'Sound Engineer',
            'password' => 'Temp-pass-123',
            'password_confirmation' => 'Temp-pass-123',
            'roles' => [$this->roleId('Technician')],
        ])->assertRedirect();

        $user = User::where('email', 'kemi@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Technician'));
        $this->assertTrue(Hash::check('Temp-pass-123', $user->password));
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => 'User', 'auditable_id' => (string) $user->id]);
        Notification::assertSentTo($user, WelcomeToNebo::class);
    }

    public function test_emails_must_be_unique_regardless_of_case(): void
    {
        $admin = $this->superAdmin();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)->post('/app/users', [
            'name' => 'Dup', 'email' => 'Taken@Example.com', 'password' => 'Temp-pass-123', 'password_confirmation' => 'Temp-pass-123',
        ])->assertSessionHasErrors('email');
    }

    public function test_password_is_never_written_to_the_audit_log(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/app/users', [
            'name' => 'Secret', 'email' => 'secret@example.com', 'password' => 'Temp-pass-123', 'password_confirmation' => 'Temp-pass-123',
        ]);

        $this->assertGreaterThan(0, AuditLog::count());
        foreach (AuditLog::all() as $log) {
            $this->assertStringNotContainsString('$2y$', json_encode($log->new_values).json_encode($log->old_values));
        }
    }

    public function test_changing_roles_is_audited_and_notifies_the_user(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();
        $user = $this->userWithRole('Crew');

        $this->actingAs($admin)->put("/app/users/{$user->id}", [
            'name' => $user->name, 'email' => $user->email, 'roles_submitted' => 1, 'roles' => [$this->roleId('Technician')],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Technician'], $user->fresh()->getRoleNames()->all());
        $this->assertDatabaseHas('audit_logs', ['event' => 'roles_changed', 'auditable_id' => (string) $user->id]);
        Notification::assertSentTo($user, AccountRolesChanged::class);
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->superAdmin();
        $this->superAdmin(); // another admin exists, so only the self-rule applies

        $this->actingAs($admin)->put("/app/users/{$admin->id}/status", ['active' => 0])->assertForbidden();
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_last_super_admin_cannot_lose_the_role(): void
    {
        $admin = $this->superAdmin();
        $other = $this->superAdmin();
        $other->update(['is_active' => false]); // inactive admins don't count

        $this->actingAs($admin)->put("/app/users/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'roles_submitted' => 1, 'roles' => [$this->roleId('Viewer')],
        ])->assertSessionHasErrors('roles');

        $this->assertTrue($admin->fresh()->isSuperAdmin());
    }

    public function test_deactivated_user_is_blocked(): void
    {
        $admin = $this->superAdmin();
        $user = $this->userWithRole('Crew');

        $this->actingAs($admin)->put("/app/users/{$user->id}/status", ['active' => 0])->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['event' => 'updated', 'auditable_id' => (string) $user->id]);
    }

    public function test_user_manager_cannot_grant_super_administrator(): void
    {
        // A custom role that can manage users but holds few permissions itself.
        $role = Role::create(['name' => 'HR', 'guard_name' => 'web']);
        $role->syncPermissions(['users.view', 'users.create', 'users.update', 'dashboard.view']);
        $hr = User::factory()->create();
        $hr->assignRole($role);

        $this->actingAs($hr)->post('/app/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'password' => 'Temp-pass-123', 'password_confirmation' => 'Temp-pass-123',
            'roles' => [$this->roleId(PermissionCatalog::SUPER_ADMIN)],
        ])->assertSessionHasErrors('roles');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_user_manager_cannot_grant_roles_with_more_permissions_than_they_hold(): void
    {
        $role = Role::create(['name' => 'HR', 'guard_name' => 'web']);
        $role->syncPermissions(['users.view', 'users.update', 'dashboard.view']);
        $hr = User::factory()->create();
        $hr->assignRole($role);
        $target = User::factory()->create();

        $this->actingAs($hr)->put("/app/users/{$target->id}", [
            'name' => $target->name, 'email' => $target->email, 'roles_submitted' => 1, 'roles' => [$this->roleId('Operations Manager')],
        ])->assertSessionHasErrors('roles');

        // Escalating themselves is equally blocked.
        $this->actingAs($hr)->put("/app/users/{$hr->id}", [
            'name' => $hr->name, 'email' => $hr->email, 'roles_submitted' => 1, 'roles' => [$role->id, $this->roleId('Operations Manager')],
        ])->assertSessionHasErrors('roles');

        $this->assertFalse($target->fresh()->hasRole('Operations Manager'));
        $this->assertFalse($hr->fresh()->hasRole('Operations Manager'));
    }

    public function test_non_super_admin_cannot_edit_a_super_admin(): void
    {
        $role = Role::create(['name' => 'HR', 'guard_name' => 'web']);
        $role->syncPermissions(['users.view', 'users.update', 'users.deactivate']);
        $hr = User::factory()->create();
        $hr->assignRole($role);
        $admin = $this->superAdmin();

        $this->actingAs($hr)->put("/app/users/{$admin->id}", ['name' => 'Renamed', 'email' => $admin->email])->assertForbidden();
        $this->actingAs($hr)->put("/app/users/{$admin->id}/status", ['active' => 0])->assertSessionHasErrors('user');
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_resets_a_password(): void
    {
        $admin = $this->superAdmin();
        $user = $this->userWithRole('Crew');

        $this->actingAs($admin)->put("/app/users/{$user->id}/password", ['password' => 'Brand-new-1', 'password_confirmation' => 'Brand-new-1'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Brand-new-1', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['event' => 'password_reset', 'auditable_id' => (string) $user->id]);
    }

    public function test_users_list_filters_by_search_role_and_status(): void
    {
        $admin = $this->superAdmin(['name' => 'Admin Person']);
        $this->userWithRole('Crew', ['name' => 'Rigger Rita']);
        $this->userWithRole('Technician', ['name' => 'Tech Tobi', 'is_active' => false]);

        $this->actingAs($admin)->get('/app/users?q=Rita')->assertSee('Rigger Rita')->assertDontSee('Tech Tobi');
        $this->actingAs($admin)->get('/app/users?status=inactive')->assertSee('Tech Tobi')->assertDontSee('Rigger Rita');
        $this->actingAs($admin)->get('/app/users?role='.$this->roleId('Crew'))->assertSee('Rigger Rita')->assertDontSee('Tech Tobi');
        $this->actingAs($admin)->get('/app/users?q=zzzz')->assertSee('No users found');
    }

    public function test_profile_update_and_password_change(): void
    {
        $user = $this->userWithRole('Crew');

        $this->actingAs($user)->put('/app/profile', ['name' => 'New Name', 'phone' => '+2348031234567', 'job_title' => 'Rigger'])->assertSessionHasNoErrors();
        $this->assertSame('New Name', $user->fresh()->name);

        $this->actingAs($user)->put('/app/profile/password', ['current_password' => 'wrong', 'password' => 'Another-pass-1', 'password_confirmation' => 'Another-pass-1'])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/app/profile/password', ['current_password' => 'password', 'password' => 'Another-pass-1', 'password_confirmation' => 'Another-pass-1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Another-pass-1', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['event' => 'password_changed', 'auditable_id' => (string) $user->id]);
    }
}
