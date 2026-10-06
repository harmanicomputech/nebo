<?php

namespace App\Support\Search;

use App\Models\Role;
use App\Models\User;

class RoleSearch implements SearchProvider
{
    public function label(): string
    {
        return 'Roles';
    }

    public function icon(): string
    {
        return 'shield-check';
    }

    public function authorize(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function search(string $term, int $limit): array
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return Role::query()->where('name', 'like', $like)->orderBy('name')->limit($limit)->get()
            ->map(fn (Role $role) => [
                'title' => $role->name,
                'subtitle' => $role->description,
                'url' => route('app.roles.edit', $role),
            ])->all();
    }
}
