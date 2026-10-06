<?php

namespace App\Services\Inventory;

use App\Enums\AssetStatusGroup;
use App\Enums\InventoryTransactionType as T;
use App\Models\AssetStatus;
use App\Models\Equipment;
use App\Models\EquipmentAsset;
use App\Models\Location;
use App\Models\User;
use App\Support\Lookups;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change to a serialized asset's status, location or condition goes
 * through here so it is validated and written to the ledger.
 *
 * Rules:
 *  - Statuses marked is_manual = false (reserved, allocated, checked out, …)
 *    belong to the allocation engine: people can't set them, and an asset in
 *    one can't be changed or moved by hand.
 *  - Moving an asset out of, or into, a lost/retired status needs inventory.archive.
 *  - Recording a condition that blocks allocation (damaged, critical, needs
 *    inspection) takes the asset out of service via the condition's sets_status.
 */
class AssetService
{
    public function __construct(
        private InventoryLedger $ledger,
        private AssetTagGenerator $tags,
        private Lookups $lookups,
    ) {}

    /**
     * Registers one or more units of a serialized item. With $count > 1, tags
     * are generated and serial/barcode are left blank (they are per unit).
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, EquipmentAsset>
     */
    public function register(Equipment $equipment, array $data, int $count = 1): Collection
    {
        if (! $equipment->isSerialized()) {
            throw ValidationException::withMessages(['equipment' => 'This item is tracked by quantity. Receive stock instead of adding assets.']);
        }

        if (! $equipment->is_active || $equipment->trashed()) {
            throw ValidationException::withMessages(['equipment' => 'This item is archived or inactive.']);
        }

        if ($count < 1 || $count > 200) {
            throw ValidationException::withMessages(['count' => 'Add between 1 and 200 units at a time.']);
        }

        $status = AssetStatus::findOrFail($data['status_id'] ?? AssetStatus::byCode('available')->id);
        $this->assertManualTarget($status);

        if (empty($data['asset_tag']) && ! $equipment->asset_prefix) {
            throw ValidationException::withMessages(['asset_tag' => 'Enter an asset tag, or give this item an asset prefix so tags can be generated.']);
        }

        return DB::transaction(function () use ($equipment, $data, $count, $status) {
            $created = collect();

            for ($i = 0; $i < $count; $i++) {
                $single = $count === 1;
                $asset = EquipmentAsset::create([
                    'equipment_id' => $equipment->id,
                    'asset_tag' => $single && ! empty($data['asset_tag']) ? strtoupper(trim($data['asset_tag'])) : $this->tags->next($equipment->asset_prefix),
                    'serial_number' => $single ? ($data['serial_number'] ?? null) : null,
                    'barcode' => $single ? ($data['barcode'] ?? null) : null,
                    'status_id' => $status->id,
                    'condition' => $data['condition'] ?? 'good',
                    'location_id' => $data['location_id'] ?? null,
                    'purchase_date' => $data['purchase_date'] ?? null,
                    'purchase_cost_kobo' => $data['purchase_cost_kobo'] ?? null,
                    'current_value_kobo' => $data['current_value_kobo'] ?? $data['purchase_cost_kobo'] ?? null,
                    'supplier' => $data['supplier'] ?? null,
                    'warranty_expires_on' => $data['warranty_expires_on'] ?? null,
                    'next_maintenance_due_on' => $data['next_maintenance_due_on'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                $this->ledger->record(! empty($data['purchase_date']) ? T::Purchased : T::Added, $equipment, $asset, [
                    'to_location_id' => $asset->location_id,
                    'to_status_id' => $status->id,
                    'to_condition' => $asset->condition,
                    'note' => $data['ledger_note'] ?? null,
                ]);

                $created->push($asset);
            }

            return $created;
        });
    }

    public function changeStatus(User $actor, EquipmentAsset $asset, AssetStatus $status, ?string $note = null): void
    {
        if ($asset->status_id === $status->id) {
            return;
        }

        $this->assertManuallyManaged($asset);
        $this->assertManualTarget($status);

        $leavingTerminal = $asset->status->group->isTerminal();
        $enteringTerminal = $status->group->isTerminal();

        if (($leavingTerminal || $enteringTerminal) && ! $actor->can('inventory.archive')) {
            throw ValidationException::withMessages(['status_id' => 'Only people who can archive or retire equipment can mark assets lost or retired, or bring them back.']);
        }

        DB::transaction(function () use ($asset, $status, $note) {
            $from = $asset->status_id;
            $asset->update(['status_id' => $status->id]);

            $this->ledger->record($status->group === AssetStatusGroup::Retired ? T::Retired : T::StatusChanged, $asset->equipment, $asset, [
                'from_status_id' => $from,
                'to_status_id' => $status->id,
                'from_location_id' => $asset->location_id,
                'to_location_id' => $asset->location_id,
                'note' => $note,
            ]);
        });

        $asset->load('status');
    }

    public function move(EquipmentAsset $asset, Location $location, ?string $note = null): void
    {
        if ($asset->location_id === $location->id) {
            return;
        }

        $this->assertManuallyManaged($asset);

        if (! $location->is_active || $location->trashed()) {
            throw ValidationException::withMessages(['location_id' => 'Choose an active location.']);
        }

        DB::transaction(function () use ($asset, $location, $note) {
            $from = $asset->location_id;
            $asset->update(['location_id' => $location->id]);

            $this->ledger->record(T::Transferred, $asset->equipment, $asset, [
                'from_location_id' => $from,
                'to_location_id' => $location->id,
                'from_status_id' => $asset->status_id,
                'to_status_id' => $asset->status_id,
                'note' => $note,
            ]);
        });
    }

    /**
     * Records an inspection result. A condition that blocks allocation takes
     * an in-service asset out of service (status from the condition's
     * meta.sets_status), so damaged kit can't be allocated by mistake.
     */
    public function recordCondition(EquipmentAsset $asset, string $condition, ?string $note = null): void
    {
        if (! in_array($condition, $this->lookups->activeKeys('condition'), true)) {
            throw ValidationException::withMessages(['condition' => 'Choose a valid condition.']);
        }

        DB::transaction(function () use ($asset, $condition, $note) {
            $fromCondition = $asset->condition;
            $fromStatus = $asset->status_id;
            $changes = ['condition' => $condition, 'last_inspected_at' => now()];

            $blocks = (bool) $this->lookups->meta('condition', $condition, 'blocks_allocation', false);
            $setsStatus = $this->lookups->meta('condition', $condition, 'sets_status');

            if ($blocks && $setsStatus && $asset->status->is_allocatable) {
                $changes['status_id'] = AssetStatus::byCode($setsStatus)->id;
            }

            $asset->update($changes);

            $this->ledger->record(T::ConditionChanged, $asset->equipment, $asset, [
                'from_condition' => $fromCondition,
                'to_condition' => $condition,
                'from_status_id' => $fromStatus,
                'to_status_id' => $asset->status_id,
                'from_location_id' => $asset->location_id,
                'to_location_id' => $asset->location_id,
                'note' => $note,
            ]);
        });

        $asset->load('status');
    }

    public function archive(EquipmentAsset $asset, ?string $note = null): void
    {
        $this->assertManuallyManaged($asset);

        DB::transaction(function () use ($asset, $note) {
            $this->ledger->record(T::Archived, $asset->equipment, $asset, ['from_location_id' => $asset->location_id, 'from_status_id' => $asset->status_id, 'note' => $note]);
            $asset->delete();
        });
    }

    public function restore(EquipmentAsset $asset): void
    {
        DB::transaction(function () use ($asset) {
            $asset->restore();
            $this->ledger->record(T::Restored, $asset->equipment, $asset, ['to_location_id' => $asset->location_id, 'to_status_id' => $asset->status_id]);
        });
    }

    private function assertManuallyManaged(EquipmentAsset $asset): void
    {
        if (! $asset->status->is_manual) {
            throw ValidationException::withMessages(['status_id' => "{$asset->asset_tag} is {$asset->status->label}. Its status is managed by event allocation and returns, not by hand."]);
        }
    }

    private function assertManualTarget(AssetStatus $status): void
    {
        if (! $status->is_manual || ! $status->is_active) {
            throw ValidationException::withMessages(['status_id' => "{$status->label} can't be set by hand."]);
        }
    }
}
