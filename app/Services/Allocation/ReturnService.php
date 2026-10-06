<?php

namespace App\Services\Allocation;

use App\Enums\AllocationState;
use App\Enums\InventoryTransactionType as T;
use App\Enums\ReturnOutcome;
use App\Enums\StockBucket;
use App\Models\AssetStatus;
use App\Models\EquipmentAllocation;
use App\Models\Event;
use App\Models\Location;
use App\Models\ReturnCheck;
use App\Models\User;
use App\Notifications\EquipmentReturnIssues;
use App\Services\Inventory\InventoryLedger;
use App\Services\Inventory\StockService;
use App\Support\Audit\Audit;
use App\Support\Recipients;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Checking equipment back in after an event (brief §27). Serialized units are
 * checked individually; bulk lines are split into returned, missing and
 * damaged quantities. Missing units are written off (bulk) or marked Lost,
 * damaged ones quarantined or marked Damaged, and the right people are told.
 */
class ReturnService
{
    public function __construct(private InventoryLedger $ledger, private StockService $stock) {}

    /**
     * @return Collection<int, EquipmentAllocation>
     */
    public function outstanding(Event $event): Collection
    {
        return $event->allocations()->where('state', AllocationState::CheckedOut)
            ->with(['asset.status', 'equipment', 'location', 'event'])->get()
            ->sortBy(fn ($a) => [$a->equipment->name, $a->asset?->asset_tag])->values();
    }

    /**
     * @param  array<int, array{outcome?: string, returned?: int, missing?: int, damaged?: int, note?: ?string}>  $lines  keyed by allocation id
     */
    public function process(User $actor, Event $event, array $lines, int $returnLocationId, ?string $notes = null): ReturnCheck
    {
        $outstanding = $this->outstanding($event)->keyBy('id');
        $lines = array_intersect_key($lines, $outstanding->all());

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Record at least one item.']);
        }

        $location = Location::findOrFail($returnLocationId);

        $check = DB::transaction(function () use ($actor, $event, $lines, $outstanding, $location, $notes) {
            $check = $event->returnChecks()->create(['user_id' => $actor->id, 'user_name' => $actor->name, 'notes' => $notes]);
            $counts = ['returned' => 0, 'missing' => 0, 'damaged' => 0];

            foreach ($lines as $id => $line) {
                $allocation = $outstanding[$id];
                $allocation->asset
                    ? $this->returnAsset($check, $allocation, ReturnOutcome::from($line['outcome'] ?? 'returned'), $location, $line['note'] ?? null, $counts)
                    : $this->returnBulk($check, $allocation, $line, $counts);
            }

            $check->update(['returned_count' => $counts['returned'], 'missing_count' => $counts['missing'], 'damaged_count' => $counts['damaged']]);
            Audit::record('returned', "Check-in for {$event->reference}: {$counts['returned']} returned, {$counts['missing']} missing, {$counts['damaged']} damaged or needing attention", $event);

            return $check;
        });

        if ($check->missing_count || $check->damaged_count) {
            Notification::send(Recipients::withPermission('inventory.update'), new EquipmentReturnIssues($event, $check));
        }

        return $check;
    }

    /**
     * @param  array{returned: int, missing: int, damaged: int}  $counts
     */
    private function returnAsset(ReturnCheck $check, EquipmentAllocation $allocation, ReturnOutcome $outcome, Location $location, ?string $note, array &$counts): void
    {
        $asset = $allocation->asset;
        $fromStatus = $asset->status_id;
        $fromLocation = $asset->location_id;
        $fromCondition = $asset->condition;

        $allocation->update(['state' => AllocationState::Returned, 'returned_at' => now(), 'return_outcome' => $outcome->value]);

        $changes = ['status_id' => AssetStatus::byCode($outcome->assetStatus())->id];
        if ($outcome !== ReturnOutcome::Missing) {
            $changes['location_id'] = $location->id;
        }
        if ($outcome->condition()) {
            $changes['condition'] = $outcome->condition();
            $changes['last_inspected_at'] = now();
        }
        // Still reserved for a later event? Then it's Allocated, not Available.
        if ($outcome === ReturnOutcome::Returned && $asset->allocations()->where('state', AllocationState::Reserved)->exists()) {
            $changes['status_id'] = AssetStatus::byCode('allocated')->id;
        }
        $asset->forceFill($changes)->save();

        $this->ledger->record(match ($outcome) {
            ReturnOutcome::Missing => T::Lost,
            ReturnOutcome::Damaged => T::Damaged,
            default => T::Returned,
        }, $allocation->equipment, $asset, [
            'event_id' => $allocation->event_id,
            'from_status_id' => $fromStatus, 'to_status_id' => $asset->status_id,
            'from_location_id' => $fromLocation, 'to_location_id' => $outcome === ReturnOutcome::Missing ? null : $location->id,
            'from_condition' => $fromCondition, 'to_condition' => $asset->condition,
            'note' => trim($outcome->label().' after '.$allocation->event->reference.($note ? ": {$note}" : '')),
        ]);

        $check->items()->create(['allocation_id' => $allocation->id, 'outcome' => $outcome, 'quantity' => 1, 'location_id' => $location->id, 'note' => $note]);
        $counts[match ($outcome) {
            ReturnOutcome::Returned => 'returned', ReturnOutcome::Missing => 'missing', default => 'damaged'
        }]++;
    }

    /**
     * @param  array{returned?: int, missing?: int, damaged?: int, note?: ?string}  $line
     * @param  array{returned: int, missing: int, damaged: int}  $counts
     */
    private function returnBulk(ReturnCheck $check, EquipmentAllocation $allocation, array $line, array &$counts): void
    {
        $returned = max(0, (int) ($line['returned'] ?? 0));
        $missing = max(0, (int) ($line['missing'] ?? 0));
        $damaged = max(0, (int) ($line['damaged'] ?? 0));

        if ($returned + $missing + $damaged !== $allocation->quantity) {
            throw ValidationException::withMessages(["lines.{$allocation->id}" => "{$allocation->equipment->name}: returned, missing and damaged must add up to the {$allocation->quantity} sent."]);
        }

        $equipment = $allocation->equipment;
        $source = $allocation->location ?? Location::findOrFail($allocation->location_id);
        $note = $line['note'] ?? null;
        $allocation->update(['state' => AllocationState::Returned, 'returned_at' => now(), 'return_outcome' => $missing || $damaged ? 'partial' : 'returned']);

        if ($returned) {
            $this->ledger->record(T::Returned, $equipment, null, ['event_id' => $allocation->event_id, 'quantity' => $returned, 'to_location_id' => $source->id, 'note' => "Returned after {$allocation->event->reference}"]);
            $check->items()->create(['allocation_id' => $allocation->id, 'outcome' => ReturnOutcome::Returned, 'quantity' => $returned, 'location_id' => $source->id, 'note' => $note]);
        }
        if ($missing) {
            $this->stock->writeOff($equipment, $source, StockBucket::Available, $missing, "Missing after {$allocation->event->reference}".($note ? ": {$note}" : ''));
            $check->items()->create(['allocation_id' => $allocation->id, 'outcome' => ReturnOutcome::Missing, 'quantity' => $missing, 'location_id' => $source->id, 'note' => $note]);
        }
        if ($damaged) {
            $this->stock->moveBucket($equipment, $source, StockBucket::Available, StockBucket::Quarantine, $damaged, "Damaged at {$allocation->event->reference}".($note ? ": {$note}" : ''));
            $check->items()->create(['allocation_id' => $allocation->id, 'outcome' => ReturnOutcome::Damaged, 'quantity' => $damaged, 'location_id' => $source->id, 'note' => $note]);
        }

        $counts['returned'] += $returned;
        $counts['missing'] += $missing;
        $counts['damaged'] += $damaged;
    }
}
