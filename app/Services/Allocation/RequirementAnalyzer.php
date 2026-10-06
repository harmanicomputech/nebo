<?php

namespace App\Services\Allocation;

use App\Enums\AllocationState;
use App\Models\Equipment;
use App\Models\EquipmentAsset;
use App\Models\EquipmentRequirement;
use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * For each requirement of an event: how many are needed, allocated and still
 * available for the event's dates, the shortage, which other events hold the
 * rest, alternatives in the same category, and allocated units that have
 * since gone out of service (brief §24).
 */
class RequirementAnalyzer
{
    public function __construct(private AvailabilityService $availability) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function analyze(Event $event): Collection
    {
        $requirements = $event->requirements()->with('equipment.category.parent')->get();
        $allocations = $event->allocations()->active()->with(['asset.status', 'asset.location', 'location'])->get()->groupBy('equipment_id');

        return $requirements->map(function (EquipmentRequirement $req) use ($event, $allocations) {
            $equipment = $req->equipment;
            $mine = $allocations[$equipment->id] ?? collect();
            $allocated = (int) $mine->sum('quantity');
            $availability = $this->availability->forEvent($equipment, $event);
            // Units this event could hold in total for its dates (its own holds are excluded from "held").
            $capacity = $availability->available;
            $shortage = max(0, $req->quantity - $capacity);
            $unserviceable = $mine->filter(fn ($a) => $a->asset && ! $this->availability->isAssetServiceable($a->asset))->values();

            return [
                'requirement' => $req,
                'equipment' => $equipment,
                'required' => $req->quantity,
                'allocated' => $allocated,
                'available' => max(0, $capacity - $allocated),
                'shortage' => $shortage,
                'conflicts' => $availability->conflicts,
                'alternatives' => $shortage > 0 ? $this->alternatives($event, $equipment) : collect(),
                'allocations' => $mine,
                'unserviceable' => $unserviceable,
                // Free units not already on this event, for choosing specific tags (capped for the UI).
                'freeAssets' => $equipment->isSerialized()
                    ? EquipmentAsset::with('location')->whereIn('id', array_diff($availability->assetIds, $mine->pluck('asset_id')->all()))->orderBy('asset_tag')->limit(100)->get()
                    : collect(),
                'checkedOut' => $mine->where('state', AllocationState::CheckedOut)->count(),
                'status' => match (true) {
                    $unserviceable->isNotEmpty() => 'attention',
                    $allocated >= $req->quantity => 'allocated',
                    $shortage > 0 => 'shortage',
                    default => 'partial',
                },
            ];
        });
    }

    /** Requirements not yet fully allocated (cheap; used by the calendar and lists). */
    public function hasOpenRequirements(Event $event): bool
    {
        $allocated = $event->allocations()->active()->selectRaw('equipment_id, sum(quantity) as qty')->groupBy('equipment_id')->pluck('qty', 'equipment_id');

        return $event->requirements()->get()->contains(fn ($r) => (int) ($allocated[$r->equipment_id] ?? 0) < $r->quantity);
    }

    /**
     * Other items in the same category family with free units for the event's dates.
     *
     * @return Collection<int, array{equipment: Equipment, available: int}>
     */
    private function alternatives(Event $event, Equipment $equipment): Collection
    {
        // Look across the whole family: a wash can stand in for a spot (siblings under one parent).
        $category = $equipment->category;
        $family = $category?->parent_id ? $category->parent->selfAndChildIds() : ($category?->selfAndChildIds() ?? [$equipment->category_id]);

        return Equipment::query()->whereIn('category_id', $family)->whereKeyNot($equipment->id)
            ->where('is_active', true)->limit(8)->get()
            ->map(fn (Equipment $e) => ['equipment' => $e, 'available' => $this->availability->forEvent($e, $event)->available])
            ->filter(fn ($a) => $a['available'] > 0)->sortByDesc('available')->take(3)->values();
    }
}
