<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use App\Support\SampleData;
use Illuminate\Database\Seeder;

/**
 * Sample sign-in accounts, one per default role, all with the password
 * SampleData::PASSWORD. Removed with the rest of the sample data (D71).
 * Never run in production (DatabaseSeeder guards this).
 */
class DemoUsersSeeder extends Seeder
{
    private const PHONES = ['ada.okafor' => '+2348034152290', 'tunde.bakare' => '+2348057721364', 'chiamaka.eze' => '+2348068830417', 'ibrahim.musa' => '+2348039914052', 'funmi.adeyemi' => '+2348090346615', 'emeka.nwosu' => '+2347031187524', 'bayo.ogun' => '+2348128864309', 'grace.udo' => '+2348165520731'];

    public function run(): void
    {
        $people = [
            PermissionCatalog::SUPER_ADMIN => ['Ada Okafor', 'ada.okafor@nebostage.com', 'Managing Director'],
            'Operations Manager' => ['Tunde Bakare', 'tunde.bakare@nebostage.com', 'Operations Manager'],
            'Inventory Manager' => ['Chiamaka Eze', 'chiamaka.eze@nebostage.com', 'Warehouse Lead'],
            'Production Manager' => ['Ibrahim Musa', 'ibrahim.musa@nebostage.com', 'Production Manager'],
            'Finance / Commercial' => ['Funmi Adeyemi', 'funmi.adeyemi@nebostage.com', 'Commercial Manager'],
            'Technician' => ['Emeka Nwosu', 'emeka.nwosu@nebostage.com', 'Lighting Technician'],
            'Crew' => ['Bayo Ogun', 'bayo.ogun@nebostage.com', 'Stage Crew'],
            'Viewer' => ['Grace Udo', 'grace.udo@nebostage.com', 'Account Executive'],
        ];

        foreach ($people as $role => [$name, $email, $title]) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'job_title' => $title,
                'phone' => self::PHONES[strstr($email, '@', true)] ?? null,
                'password' => SampleData::PASSWORD,
                'is_active' => true,
            ]);
            $user->syncRoles([$role]);
        }
    }
}
