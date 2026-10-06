<?php

namespace App\Support\Search;

use App\Models\Equipment;
use App\Models\User;

class EquipmentSearch implements SearchProvider
{
    public function label(): string
    {
        return 'Equipment';
    }

    public function icon(): string
    {
        return 'boxes';
    }

    public function authorize(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function search(string $term, int $limit): array
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return Equipment::query()->with('category')
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('manufacturer', 'like', $like)->orWhere('model', 'like', $like))
            ->orderBy('name')->limit($limit)->get()
            ->map(fn (Equipment $e) => [
                'title' => $e->name,
                'subtitle' => $e->sku.' · '.$e->category?->name.' · '.$e->tracking_mode->label(),
                'url' => route('app.inventory.equipment.show', $e),
            ])->all();
    }
}
