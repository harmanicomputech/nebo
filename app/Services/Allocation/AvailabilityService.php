<?php

namespace App\Services\Allocation;

use App\Enums\AssetStatusGroup;
use App\Enums\StockBucket;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentAsset;
use App\Models\Event;
use App\Models\StockLevel;
use App\Services\Maintenance\MaintenanceBlocker;
use App\Support\Lookups;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The availability engine (brief §21, ARCHITECTURE D8/D43).
 *
 * A serialized unit is available for a window when it is serviceable (status
 * group available, committed or out, and a condition that doesn't block
 * allocation), no active allocation of another event overlaps the window, and
 * no blocker (scheduled maintenance) covers it. Bulk stock is the owned
 * available bucket minus quantities held by overlapping allocations.
 */
class AvailabilityService
{
    /** @var list<class-string<AvailabilityBlocker>> */
    public const BLOCKERS = [MaintenanceBlocker::class];

    public function __construct(private Lookups $lookups) {}

    /**
     * The event's hold window, widened by the turnaround buffer setting.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function window(Event $event): array
    {
        $buffer = max(0, (int) Settings::get('availability.buffer_hours', 0));

        return [
            CarbonImmutable::parse($event->setup_starts_at)->subHours($buffer),
            CarbonImmutable::parse($event->breakdown_ends_at)->addHours($buffer),
        ];
    }

    public function forEvent(Equipment $equipment, Event $event): Availability
    {
        [$from, $to] = $this->window($event);

        return $this->forEquipment($equipment, $from, $to, $event->id);
    }

    public function forEquipment(Equipment $equipment, CarbonInterface $from, CarbonInterface $to, ?int $excludeEventId = null): Availability
    {
        $holds = EquipmentAllocation::query()->overlapping($from, $to)
            ->where('equipment_id', $equipment->id)
            ->when($excludeEventId, fn (Builder $q) => $q->where('event_id', '!=', $excludeEventId))
            ->get(['event_id', 'asset_id', 'quantity']);

        $conflicts = $this->conflicts($holds);

        if (! $equipment->isSerialized()) {
            $owned = (int) StockLevel::where('equipment_id', $equipment->id)->where('bucket', StockBucket::Available)->sum('quantity');
            $held = (int) $holds->sum('quantity');

            return new Availability($owned, max(0, $owned - $held), $held, 0, $conflicts);
        }

        $pool = $this->serviceableAssets($equipment)->pluck('id')->all();
        $busy = $holds->pluck('asset_id')->filter()->unique()->all();
        $blocked = array_values(array_intersect($pool, $this->blockedAssetIds($equipment, $from, $to)));
        $free = array_values(array_diff($pool, $busy, $blocked));

        return new Availability(count($pool), count($free), count(array_intersect($pool, $busy)), count($blocked), $conflicts, $free);
    }

    /**
     * Serialized units that can go out at all, regardless of dates.
     *
     * @return Builder<EquipmentAsset>
     */
    public function serviceableAssets(Equipment $equipment): Builder
    {
        $blocking = $this->lookups->keysWhere('condition', 'blocks_allocation');

        return EquipmentAsset::query()->where('equipment_id', $equipment->id)
            ->whereHas('status', fn ($s) => $s->whereIn('group', [AssetStatusGroup::Available->value, AssetStatusGroup::Committed->value, AssetStatusGroup::Out->value]))
            ->when($blocking, fn ($q) => $q->whereNotIn('condition', $blocking));
    }

    public function isAssetServiceable(EquipmentAsset $asset): bool
    {
        return in_array($asset->status->group, [AssetStatusGroup::Available, AssetStatusGroup::Committed, AssetStatusGroup::Out], true)
            && ! $asset->conditionBlocksAllocation();
    }

    /**
     * @return list<int>
     */
    public function blockedAssetIds(Equipment $equipment, CarbonInterface $from, CarbonInterface $to): array
    {
        $ids = [];
        foreach (self::BLOCKERS as $blocker) {
            $ids = array_merge($ids, app($blocker)->blockedAssetIds($equipment, $from, $to));
        }

        return array_values(array_unique($ids));
    }

    /**
     * Free units per item per day, for the availability calendar. Loads all
     * overlapping allocations for the range once.
     *
     * @param  Collection<int, Equipment>  $items
     * @return array<int, list<array{date: CarbonImmutable, available: int, total: int, held: int}>>
     */
    public function timeline(Collection $items, CarbonImmutable $from, int $days): array
    {
        $to = $from->addDays($days);
        $holds = EquipmentAllocation::query()->overlapping($from->utc(), $to->utc())
            ->whereIn('equipment_id', $items->pluck('id'))
            ->get(['equipment_id', 'asset_id', 'quantity', 'hold_starts_at', 'hold_ends_at'])
            ->groupBy('equipment_id');

        $result = [];
        foreach ($items as $item) {
            $total = $item->isSerialized()
                ? $this->serviceableAssets($item)->count()
                : (int) StockLevel::where('equipment_id', $item->id)->where('bucket', StockBucket::Available)->sum('quantity');

            for ($i = 0; $i < $days; $i++) {
                $dayStart = $from->addDays($i);
                $dayEnd = $dayStart->addDay();
                $dayHolds = ($holds[$item->id] ?? collect())->filter(fn ($h) => $h->hold_starts_at < $dayEnd && $h->hold_ends_at > $dayStart);
                $held = $item->isSerialized() ? $dayHolds->pluck('asset_id')->unique()->count() : (int) $dayHolds->sum('quantity');
                $blocked = $item->isSerialized() && self::BLOCKERS ? count($this->blockedAssetIds($item, $dayStart, $dayEnd)) : 0;

                $result[$item->id][] = ['date' => $dayStart, 'total' => $total, 'held' => $held, 'available' => max(0, $total - $held - $blocked)];
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, EquipmentAllocation>  $holds
     * @return Collection<int, array{event: Event, quantity: int}>
     */
    private function conflicts(Collection $holds): Collection
    {
        if ($holds->isEmpty()) {
            return collect();
        }

        $events = Event::withTrashed()->whereIn('id', $holds->pluck('event_id')->unique())->get()->keyBy('id');

        return $holds->groupBy('event_id')->map(fn ($g, $eventId) => ['event' => $events[$eventId], 'quantity' => (int) $g->sum('quantity')])
            ->sortBy(fn ($c) => $c['event']->setup_starts_at)->values();
    }
}
