<?php

namespace App\Support\Search;

use App\Models\Staff;
use App\Models\User;

class StaffSearch implements SearchProvider
{
    public function label(): string
    {
        return 'Staff & crew';
    }

    public function icon(): string
    {
        return 'contact';
    }

    public function authorize(User $user): bool
    {
        return $user->can('staff.view');
    }

    public function search(string $term, int $limit): array
    {
        return Staff::query()->search($term)->orderBy('name')->limit($limit)->get()
            ->map(fn (Staff $s) => ['title' => $s->name, 'subtitle' => $s->roleLabel().($s->phone ? ' · '.$s->phone : ''), 'url' => route('app.staff.show', $s)])->all();
    }
}
