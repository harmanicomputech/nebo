<?php

namespace App\Http\Controllers\Internal\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentAsset;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Printable QR asset labels for one asset or every unit of an item. */
class LabelController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', EquipmentAsset::class);
        $data = $request->validate([
            'equipment' => ['nullable', 'integer'],
            'assets' => ['nullable', 'array', 'max:500'],
            'assets.*' => ['integer'],
        ]);

        abort_if(empty($data['equipment']) && empty($data['assets']), 404);

        $assets = EquipmentAsset::query()->with('equipment')
            ->when($data['equipment'] ?? null, fn ($q, $id) => $q->where('equipment_id', $id))
            ->when($data['assets'] ?? null, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->orderBy('asset_tag')->limit(500)->get();

        return view('internal.inventory.labels', [
            'assets' => $assets,
            'equipment' => isset($data['equipment']) ? Equipment::withTrashed()->find($data['equipment']) : null,
        ]);
    }
}
