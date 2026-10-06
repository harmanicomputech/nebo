<?php

namespace App\Services\Allocation;

use App\Enums\AllocationState;
use App\Enums\InventoryTransactionType as T;
use App\Models\AssetStatus;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentAsset;
use App\Models\Event;
use App\Models\User;
use App\Services\Inventory\InventoryLedger;
use App\Support\Audit\Audit;
use App\Support\Format;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reserving and releasing equipment for events. Every write locks the
 * equipment row first and re-checks availability inside the transaction, so
 * two planners can never allocate the same unit or the same stock twice (D9).
 */
class AllocationService
{
    public function __construct(private AvailabilityService $availability, private InventoryLedger $ledger) {}

    /**
     * @param  list<int>  $assetIds
     * @return Collection<int, EquipmentAllocation>
     */
    public function reserveAssets(User $actor, Event $event, Equipment $equipment, array $assetIds): Collection
    {
        $this->guard($event, $equipment, serialized: true);
        $assetIds = array_values(array_unique(array_map('intval', $assetIds)));

        if ($assetIds === []) {
            throw ValidationException::withMessages(['assets' => 'Choose at least one unit.']);
        }

        return DB::transaction(function () use ($actor, $event, $equipment, $assetIds) {
            $this->lock($equipment);
            [$from, $to] = $this->availability->window($event);
            $free = $this->availability->forEquipment($equipment, $from, $to, $event->id)->assetIds;
            $already = $event->allocations()->active()->whereIn('asset_id', $assetIds)->pluck('asset_id')->all();
            $assets = EquipmentAsset::with(['status', 'equipment'])->whereIn('id', $assetIds)->where('equipment_id', $equipment->id)->get()->keyBy('id');

            foreach ($assetIds as $id) {
                $tag = $assets[$id]->asset_tag ?? "#{$id}";
                if (! isset($assets[$id]) || in_array($id, $already, true) || ! in_array($id, $free, true)) {
                    throw ValidationException::withMessages(['assets' => in_array($id, $already, true)
                        ? "{$tag} is already allocated to this event."
                        : "{$tag} isn't available for {$event->name}'s dates. It may be booked on another event, out of service or scheduled for maintenance."]);
                }
            }

            $created = collect();
            foreach ($assetIds as $id) {
                $asset = $assets[$id];
                $allocation = $event->allocations()->create([
                    'equipment_id' => $equipment->id, 'asset_id' => $asset->id, 'location_id' => $asset->location_id, 'quantity' => 1,
                    'hold_starts_at' => $from, 'hold_ends_at' => $to, 'state' => AllocationState::Reserved, 'allocated_by' => $actor->id,
                ]);
                $this->syncAssetStatus($asset, $event, T::Allocated);
                $created->push($allocation);
            }

            Audit::record('allocated', count($assetIds).' × '.$equipment->name.' allocated to '.$event->reference.': '.$assets->only($assetIds)->pluck('asset_tag')->implode(', '), $event);

            return $created;
        });
    }

    /**
     * Picks free units automatically (by location, then tag).
     *
     * @return Collection<int, EquipmentAllocation>
     */
    public function autoReserve(User $actor, Event $event, Equipment $equipment, int $count): Collection
    {
        $free = $this->availability->forEvent($equipment, $event)->assetIds;
        $mine = $event->allocations()->active()->pluck('asset_id')->all();
        $candidates = array_values(array_diff($free, $mine));

        if ($count < 1 || count($candidates) < $count) {
            throw ValidationException::withMessages(['quantity' => 'Only '.count($candidates)." more {$equipment->name} can be allocated for these dates."]);
        }

        $ids = EquipmentAsset::whereIn('id', $candidates)->orderBy('location_id')->orderBy('asset_tag')->limit($count)->pluck('id')->all();

        return $this->reserveAssets($actor, $event, $equipment, $ids);
    }

    public function reserveBulk(User $actor, Event $event, Equipment $equipment, int $quantity, ?int $locationId = null): EquipmentAllocation
    {
        $this->guard($event, $equipment, serialized: false);

        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Enter a quantity of at least 1.']);
        }

        return DB::transaction(function () use ($actor, $event, $equipment, $quantity, $locationId) {
            $this->lock($equipment);
            $available = $this->availability->forEvent($equipment, $event)->available - (int) $event->allocations()->active()->where('equipment_id', $equipment->id)->sum('quantity');

            if ($quantity > $available) {
                throw ValidationException::withMessages(['quantity' => "Only {$available} {$equipment->name} can be allocated for these dates."]);
            }

            [$from, $to] = $this->availability->window($event);
            $allocation = $event->allocations()->create([
                'equipment_id' => $equipment->id, 'quantity' => $quantity,
                'location_id' => $locationId ?? $equipment->stockLevels()->where('bucket', 'available')->orderByDesc('quantity')->value('location_id'),
                'hold_starts_at' => $from, 'hold_ends_at' => $to, 'state' => AllocationState::Reserved, 'allocated_by' => $actor->id,
            ]);

            $this->ledger->record(T::Allocated, $equipment, null, ['quantity' => $quantity, 'event_id' => $event->id, 'from_location_id' => $allocation->location_id, 'note' => "Reserved for {$event->reference}"]);
            Audit::record('allocated', "{$quantity} × {$equipment->name} allocated to {$event->reference}", $event);

            return $allocation;
        });
    }

    public function release(User $actor, EquipmentAllocation $allocation, ?string $reason = null): void
    {
        if ($allocation->state !== AllocationState::Reserved) {
            throw ValidationException::withMessages(['allocation' => 'Only reserved equipment can be released. Checked-out equipment must be checked back in.']);
        }

        DB::transaction(function () use ($allocation, $reason) {
            $allocation->update(['state' => AllocationState::Released, 'released_at' => now()]);
            $allocation->loadListItem?->delete();

            if ($allocation->asset) {
                $this->syncAssetStatus($allocation->asset, $allocation->event, T::Deallocated, $reason);
            } else {
                $this->ledger->record(T::Deallocated, $allocation->equipment, null, ['quantity' => $allocation->quantity, 'event_id' => $allocation->event_id, 'note' => $reason ?? "Released from {$allocation->event->reference}"]);
            }
        });

        Audit::record('deallocated', ($allocation->asset?->asset_tag ?? $allocation->quantity.' × '.$allocation->equipment->name)." released from {$allocation->event->reference}".($reason ? ": {$reason}" : ''), $allocation->event);
    }

    /** Releases everything still only reserved (event cancelled or finished). */
    public function releaseReserved(User $actor, Event $event, string $reason): int
    {
        $reserved = $event->allocations()->where('state', AllocationState::Reserved)->with(['asset.status', 'equipment', 'event', 'loadListItem'])->get();
        $reserved->each(fn ($a) => $this->release($actor, $a, $reason));

        return $reserved->count();
    }

    /**
     * After an event's dates change: move its holds to the new window, or
     * refuse the change if any held unit is booked elsewhere in it.
     */
    public function rewindow(Event $event): void
    {
        $active = $event->allocations()->active()->with(['equipment', 'asset'])->get();
        if ($active->isEmpty()) {
            return;
        }

        [$from, $to] = $this->availability->window($event);

        foreach ($active->groupBy('equipment_id') as $allocations) {
            $equipment = $allocations->first()->equipment;
            $this->lock($equipment);
            $availability = $this->availability->forEquipment($equipment, $from, $to, $event->id);

            $clash = $equipment->isSerialized()
                ? $allocations->first(fn ($a) => ! in_array($a->asset_id, $availability->assetIds, true))
                : ($allocations->sum('quantity') > $availability->available ? $allocations->first() : null);

            if ($clash) {
                $who = $availability->conflicts->first();
                throw ValidationException::withMessages(['setup_starts_at' => 'The new dates clash with '.($clash->asset?->asset_tag ?? $equipment->name).($who ? " booked on {$who['event']->name} (".Format::date($who['event']->setup_starts_at).')' : '').'. Release or swap that equipment first.']);
            }
        }

        $event->allocations()->active()->update(['hold_starts_at' => $from, 'hold_ends_at' => $to]);
    }

    /**
     * Keeps an asset's status in step with its allocations: Allocated while
     * reserved (and not out), back to Available when nothing holds it.
     */
    public function syncAssetStatus(EquipmentAsset $asset, ?Event $event, T $type, ?string $note = null): void
    {
        $asset->loadMissing(['status', 'equipment']);
        $fromStatus = $asset->status;
        $out = $asset->allocations()->where('state', AllocationState::CheckedOut)->exists();
        $reserved = $asset->allocations()->where('state', AllocationState::Reserved)->exists();

        $target = match (true) {
            $out => null, // stays checked out / deployed
            $reserved && in_array($fromStatus->code, ['available', 'allocated', 'reserved'], true) => 'allocated',
            ! $reserved && in_array($fromStatus->code, ['allocated', 'reserved'], true) => 'available',
            default => null,
        };

        if ($target && $target !== $fromStatus->code) {
            $asset->forceFill(['status_id' => AssetStatus::byCode($target)->id])->save();
        }

        $this->ledger->record($type, $asset->equipment, $asset, [
            'event_id' => $event?->id,
            'from_status_id' => $fromStatus->id, 'to_status_id' => $asset->status_id,
            'from_location_id' => $asset->location_id, 'to_location_id' => $asset->location_id,
            'note' => $note ?? ($event ? ($type === T::Allocated ? 'Allocated to ' : 'Released from ').$event->reference : null),
        ]);
        $asset->unsetRelation('status');
    }

    private function guard(Event $event, Equipment $equipment, bool $serialized): void
    {
        if (! $event->status->holdsResources() || $event->trashed()) {
            throw ValidationException::withMessages(['event' => "{$event->name} is {$event->status->label()}; equipment can't be allocated."]);
        }

        if ($equipment->isSerialized() !== $serialized) {
            throw ValidationException::withMessages(['equipment' => $serialized ? 'This item is tracked by quantity.' : 'This item is serialized; choose units.']);
        }

        if (! $equipment->is_active || $equipment->trashed()) {
            throw ValidationException::withMessages(['equipment' => "{$equipment->name} is archived or inactive."]);
        }
    }

    /** Serialises allocation writes per equipment item (D9). */
    private function lock(Equipment $equipment): void
    {
        Equipment::query()->whereKey($equipment->id)->lockForUpdate()->first();
    }
}
