<?php

namespace App\Http\Controllers\Internal\Allocation;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Services\Allocation\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Availability calendar (brief §52): free units per item per day. */
class AvailabilityController extends Controller
{
    public const DAYS = 14;

    public function __invoke(Request $request, AvailabilityService $availability): View
    {
        abort_unless($request->user()->can('allocation.view'), 403);
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'category' => ['nullable', 'integer'],
            'equipment' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $tz = config('nebo.display_timezone');
        $from = CarbonImmutable::parse($filters['date'] ?? 'today', $tz)->startOfDay();

        $items = Equipment::query()->where('is_active', true)->with('category')
            ->when($filters['equipment'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->whereIn('category_id', EquipmentCategory::find($id)?->selfAndChildIds() ?? [0]))
            ->search($filters['q'] ?? null)
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('internal.allocation.availability', [
            'items' => $items,
            'timeline' => $availability->timeline($items->getCollection(), $from, self::DAYS),
            'from' => $from,
            'filters' => $filters,
            'categories' => EquipmentCategory::options(),
            'equipmentOptions' => Equipment::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
