<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+23480'.fake()->numerify('########'),
            'job_title' => fake()->jobTitle(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * @param  string|list<string>  $roles
     */
    public function withRole(string|array $roles): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($roles));
    }
}
