<?php

namespace App\Services\System;

use App\Models\User;
use App\Services\Inventory\InitialInventory;
use App\Support\Audit\Audit;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Artisan;

/**
 * The installer's work, split into steps short enough for one web request on
 * shared hosting (D69). Every step is safe to retry: migrations and reference
 * data are idempotent, the inventory is imported once and the admin is matched by email.
 */
class InstallSteps
{
    /**
     * @return list<array{key: string, label: string}>
     */
    public function plan(): array
    {
        return [
            ['key' => 'migrate', 'label' => 'Creating the database tables'],
            ['key' => 'reference', 'label' => 'Adding roles, permissions and reference data'],
            ['key' => 'inventory', 'label' => 'Adding your stage and LED screen inventory'],
            ['key' => 'admin', 'label' => 'Creating your administrator account'],
        ];
    }

    /**
     * @param  array{name: string, email: string, password_hash: string, company: ?string}  $admin
     */
    public function run(string $key, array $admin): void
    {
        @set_time_limit(300);

        match (true) {
            $key === 'migrate' => $this->artisan('migrate', ['--force' => true]),
            $key === 'reference' => $this->artisan('db:seed', ['--class' => ReferenceDataSeeder::class, '--force' => true]),
            $key === 'inventory' => app(InitialInventory::class)->import(),
            $key === 'admin' => $this->createAdmin($admin),
            default => throw new \InvalidArgumentException("Unknown install step {$key}."),
        };
    }

    private function artisan(string $command, array $options): void
    {
        if (Artisan::call($command, $options) !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: "{$command} failed.");
        }
    }

    private function createAdmin(array $admin): void
    {
        $user = User::withTrashed()->firstOrNew(['email' => mb_strtolower($admin['email'])]);
        $user->forceFill(['name' => $admin['name'], 'password' => $admin['password_hash'], 'is_active' => true, 'deleted_at' => null])->save();
        $user->assignRole(PermissionCatalog::SUPER_ADMIN);

        if (filled($admin['company'] ?? null)) {
            Settings::set('company.name', $admin['company']);
        }
        if (! str_ends_with($admin['email'], '.test')) {
            Settings::set('company.email', $admin['email']);
        }

        Audit::record('installed', "Installed with the web installer; {$user->email} is the Super Administrator", $user);
    }
}
