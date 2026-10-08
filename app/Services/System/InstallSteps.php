<?php

namespace App\Services\System;

use App\Models\User;
use App\Services\Inventory\InitialInventory;
use App\Support\Audit\Audit;
use App\Support\Permissions\PermissionCatalog;
use App\Support\SampleData;
use App\Support\Settings;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\HistoryDemoSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * The installer's work, split into steps short enough for one web request on
 * shared hosting (D69). Every step is safe to retry: migrations and reference
 * data are idempotent, the admin is matched by email and each sample seeder (or
 * history batch) skips itself when its data exists.
 */
class InstallSteps
{
    private const LABELS = ['DemoUsersSeeder' => 'sign-in accounts', 'BookingDemoSeeder' => 'enquiries',
        'EventsDemoSeeder' => 'crew and events', 'AllocationDemoSeeder' => 'equipment bookings', 'MaintenanceDemoSeeder' => 'repairs and servicing',
        'LogisticsDemoSeeder' => 'fleet and trips', 'CommercialDemoSeeder' => 'packages and quotations'];

    /**
     * @return list<array{key: string, label: string}>
     */
    public function plan(bool $demo): array
    {
        $steps = [
            ['key' => 'migrate', 'label' => 'Creating the database tables'],
            ['key' => 'reference', 'label' => 'Adding roles, permissions and reference data'],
            ['key' => 'inventory', 'label' => 'Adding your stage and LED screen inventory'],
            ['key' => 'admin', 'label' => 'Creating your administrator account'],
        ];

        if ($demo) {
            foreach (DatabaseSeeder::DEMO as $seeder) {
                if ($seeder === HistoryDemoSeeder::class) {
                    for ($batch = 0; $batch < HistoryDemoSeeder::batches(); $batch++) {
                        $steps[] = ['key' => "history:{$batch}", 'label' => 'Adding a year of sample history (part '.($batch + 1).' of '.HistoryDemoSeeder::batches().')'];
                    }

                    continue;
                }
                $steps[] = ['key' => 'demo:'.class_basename($seeder), 'label' => 'Adding sample data: '.(self::LABELS[class_basename($seeder)] ?? 'records')];
            }
        }

        return $steps;
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
            str_starts_with($key, 'history:') => DB::transaction(fn () => SampleData::record(fn () => app(HistoryDemoSeeder::class)->runBatch((int) substr($key, 8)))),
            str_starts_with($key, 'demo:') => $this->demo(substr($key, 5)),
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

    private function demo(string $seeder): void
    {
        $class = collect(DatabaseSeeder::DEMO)->first(fn (string $c) => class_basename($c) === $seeder)
            ?? throw new \InvalidArgumentException("Unknown demo seeder {$seeder}.");

        DB::transaction(fn () => SampleData::record(fn () => app($class)->setContainer(app())->__invoke()));
    }
}
