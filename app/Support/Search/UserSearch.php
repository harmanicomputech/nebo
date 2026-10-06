<?php

namespace App\Support\Search;

use App\Models\User;

class UserSearch implements SearchProvider
{
    public function label(): string
    {
        return 'Users';
    }

    public function icon(): string
    {
        return 'user-round';
    }

    public function authorize(User $user): bool
    {
        return $user->can('users.view');
    }

    public function search(string $term, int $limit): array
    {
        return User::query()->search($term)->orderBy('name')->limit($limit)->get()
            ->map(fn (User $user) => [
                'title' => $user->name,
                'subtitle' => $user->email.($user->is_active ? '' : ' · deactivated'),
                'url' => route('app.users.edit', $user),
            ])->all();
    }
}
