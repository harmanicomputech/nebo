<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Database\Seeder;

/**
 * Development accounts, one per default role. All use the password
 * "password" and the reserved .test domain so they are obviously demo data.
 * Never run in production (DatabaseSeeder guards this).
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $people = [
            PermissionCatalog::SUPER_ADMIN => ['Ada Okafor (Demo)', 'admin@nebostage.test', 'Managing Director'],
            'Operations Manager' => ['Tunde Bakare (Demo)', 'operations@nebostage.test', 'Operations Manager'],
            'Inventory Manager' => ['Chiamaka Eze (Demo)', 'inventory@nebostage.test', 'Warehouse Lead'],
            'Production Manager' => ['Ibrahim Musa (Demo)', 'production@nebostage.test', 'Production Manager'],
            'Finance / Commercial' => ['Funmi Adeyemi (Demo)', 'finance@nebostage.test', 'Commercial Manager'],
            'Technician' => ['Emeka Nwosu (Demo)', 'technician@nebostage.test', 'Lighting Technician'],
            'Crew' => ['Bayo Ogun (Demo)', 'crew@nebostage.test', 'Stage Crew'],
            'Viewer' => ['Grace Udo (Demo)', 'viewer@nebostage.test', 'Account Executive'],
        ];

        foreach ($people as $role => [$name, $email, $title]) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'job_title' => $title,
                'phone' => '+2348000000000',
                'password' => 'password',
                'is_active' => true,
            ]);
            $user->syncRoles([$role]);
        }
    }
}
