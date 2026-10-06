<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_super_admin_can_open_every_internal_page(): void
    {
        $admin = $this->superAdmin();
        $other = User::factory()->create();

        foreach ([
            '/app', '/app/users', '/app/users/create', "/app/users/{$other->id}/edit",
            '/app/roles', '/app/roles/create', '/app/roles/1/edit',
            '/app/audit', '/app/settings', '/app/notifications', '/app/profile', '/app/search?q=ad',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function forbiddenPages(): array
    {
        return [
            'viewer' => ['Viewer', ['/app/users', '/app/roles', '/app/audit', '/app/settings', '/app/users/create']],
            'crew' => ['Crew', ['/app/users', '/app/roles', '/app/audit', '/app/settings']],
            'technician' => ['Technician', ['/app/users', '/app/roles', '/app/audit', '/app/settings']],
            'finance' => ['Finance / Commercial', ['/app/users', '/app/roles', '/app/audit', '/app/settings']],
            'inventory manager' => ['Inventory Manager', ['/app/users', '/app/roles', '/app/audit', '/app/settings']],
        ];
    }

    /**
     * @param  list<string>  $urls
     */
    #[DataProvider('forbiddenPages')]
    public function test_roles_cannot_open_administration_pages(string $role, array $urls): void
    {
        $user = $this->userWithRole($role);

        $this->actingAs($user)->get('/app')->assertOk();

        foreach ($urls as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    public function test_operations_manager_can_view_but_not_change_users_or_settings(): void
    {
        $manager = $this->userWithRole('Operations Manager');
        $other = User::factory()->create();

        $this->actingAs($manager)->get('/app/users')->assertOk();
        $this->actingAs($manager)->get('/app/settings')->assertOk();
        $this->actingAs($manager)->get('/app/audit')->assertOk();

        $this->actingAs($manager)->get('/app/users/create')->assertForbidden();
        $this->actingAs($manager)->put("/app/users/{$other->id}", ['name' => 'X', 'email' => $other->email])->assertForbidden();
        $this->actingAs($manager)->put('/app/settings', ['company_name' => 'Hacked'])->assertForbidden();
    }

    public function test_user_without_any_role_sees_only_their_own_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/app')->assertOk();
        $this->actingAs($user)->get('/app/profile')->assertOk();
        $this->actingAs($user)->get('/app/notifications')->assertOk();
        $this->actingAs($user)->get('/app/users')->assertForbidden();
    }

    public function test_sidebar_hides_modules_the_user_cannot_access(): void
    {
        $viewer = $this->userWithRole('Viewer');

        $this->actingAs($viewer)->get('/app')
            ->assertDontSee(route('app.users.index'))
            ->assertDontSee(route('app.settings.edit'))
            ->assertSee('Coming next');

        $this->actingAs($this->superAdmin())->get('/app')
            ->assertSee(route('app.users.index'))
            ->assertSee(route('app.roles.index'));
    }

    public function test_only_the_current_section_is_highlighted(): void
    {
        $html = $this->actingAs($this->superAdmin())->get('/app/users')->getContent();

        // One active sidebar link (the breadcrumb's aria-current is a span, not a link).
        $this->assertSame(1, preg_match_all('#<a [^>]*aria-current="page"#', $html));
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('app.users.index'), '#').'"\s+aria-current="page"#', $html);
    }

    public function test_super_admin_has_permissions_added_after_role_creation(): void
    {
        $admin = $this->superAdmin();

        $this->assertTrue($admin->can('reports.financial'));
        $this->assertTrue($admin->can('some.future_permission'));
        $this->assertSame(PermissionCatalog::SUPER_ADMIN, $admin->getRoleNames()->first());
    }

    public function test_every_default_role_only_uses_catalog_permissions(): void
    {
        foreach (PermissionCatalog::defaultRoles() as $definition) {
            if ($definition['permissions'] !== '*') {
                $this->assertSame([], array_diff($definition['permissions'], PermissionCatalog::all()));
            }
        }
    }
}
