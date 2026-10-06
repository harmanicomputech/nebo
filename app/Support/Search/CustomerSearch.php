<?php

namespace App\Support\Search;

use App\Models\Customer;
use App\Models\User;

class CustomerSearch implements SearchProvider
{
    public function label(): string
    {
        return 'Customers';
    }

    public function icon(): string
    {
        return 'contact';
    }

    public function authorize(User $user): bool
    {
        return $user->can('viewAny', Customer::class);
    }

    public function search(string $term, int $limit): array
    {
        return Customer::query()->search($term)->orderBy('company')->orderBy('name')->limit($limit)->get()
            ->map(fn (Customer $c) => [
                'title' => $c->company ?: $c->name,
                'subtitle' => collect([$c->company ? $c->name : null, $c->email, $c->phone])->filter()->implode(' · '),
                'url' => route('app.customers.show', $c),
            ])->all();
    }
}
