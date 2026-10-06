<?php

namespace Tests;

use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Database\Seeders\InventoryReferenceSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RolesAndPermissionsSeeder::class, InventoryReferenceSeeder::class]);
    }

    protected function superAdmin(array $attributes = []): User
    {
        return User::factory()->withRole(PermissionCatalog::SUPER_ADMIN)->create($attributes);
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        return User::factory()->withRole($role)->create($attributes);
    }
}
