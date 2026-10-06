<?php

namespace App\Support\Search;

use App\Models\EquipmentAsset;
use App\Models\User;

class AssetSearch implements SearchProvider
{
    public function label(): string
    {
        return 'Assets';
    }

    public function icon(): string
    {
        return 'qr-code';
    }

    public function authorize(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function search(string $term, int $limit): array
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return EquipmentAsset::query()->with(['equipment', 'status', 'location'])
            ->where(fn ($q) => $q->where('asset_tag', 'like', $like)->orWhere('serial_number', 'like', $like)->orWhere('barcode', 'like', $like))
            ->orderBy('asset_tag')->limit($limit)->get()
            ->map(fn (EquipmentAsset $a) => [
                'title' => $a->asset_tag.' · '.$a->equipment->name,
                'subtitle' => $a->status->label.($a->location ? ' · '.$a->location->name : '').($a->serial_number ? ' · S/N '.$a->serial_number : ''),
                'url' => route('app.inventory.assets.show', $a),
            ])->all();
    }
}
