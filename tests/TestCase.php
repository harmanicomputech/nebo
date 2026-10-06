<?php

namespace Tests;

use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(ReferenceDataSeeder::class);
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
