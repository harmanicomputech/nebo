<?php

namespace App\Support\Search;

use App\Models\User;
use App\Models\Vehicle;

class VehicleSearch implements SearchProvider
{
    public function label(): string
    {
        return 'Vehicles';
    }

    public function icon(): string
    {
        return 'car-front';
    }

    public function authorize(User $user): bool
    {
        return $user->can('viewAny', Vehicle::class);
    }

    public function search(string $term, int $limit): array
    {
        return Vehicle::query()->search($term)->orderBy('name')->limit($limit)->get()
            ->map(fn (Vehicle $v) => [
                'title' => $v->name,
                'subtitle' => $v->registration.' · '.$v->typeLabel().' · '.$v->status->label(),
                'url' => route('app.logistics.vehicles.show', $v),
            ])->all();
    }
}
