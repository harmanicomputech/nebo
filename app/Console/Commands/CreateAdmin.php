<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Permissions\PermissionCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('nebo:create-admin {--name= : Full name} {--email= : Email address} {--password= : Password (prompted if omitted; NEBO_ADMIN_PASSWORD is also read)}')]
#[Description('Create (or promote) a Super Administrator account')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $this->callSilently('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        $email = mb_strtolower((string) ($this->option('email') ?: config('nebo.admin.email') ?: $this->ask('Email address')));
        $existing = User::withTrashed()->where('email', $email)->first();

        if ($existing) {
            $existing->restore();
            $existing->update(['is_active' => true]);
            $existing->assignRole(PermissionCatalog::SUPER_ADMIN);
            Audit::record('roles_assigned', "{$existing->name} made Super Administrator from the console", $existing);
            $this->info("{$email} is now an active Super Administrator.");

            return self::SUCCESS;
        }

        $data = [
            'name' => $this->option('name') ?: $this->ask('Full name', 'Nebo Stage Administrator'),
            'email' => $email,
            'password' => $this->option('password') ?: config('nebo.admin.password') ?: $this->secret('Password'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create($data + ['is_active' => true]);
        $user->assignRole(PermissionCatalog::SUPER_ADMIN);
        $this->info("Super Administrator {$email} created.");

        return self::SUCCESS;
    }
}
