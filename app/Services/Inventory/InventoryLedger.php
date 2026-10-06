<?php

namespace App\Services\Inventory;

use App\Enums\InventoryTransactionType;
use App\Models\Equipment;
use App\Models\EquipmentAsset;
use App\Models\InventoryTransaction;

/**
 * Writes the immutable movement ledger (ARCHITECTURE D10). Callers run
 * inside the same transaction as the state change, so the two never diverge.
 */
class InventoryLedger
{
    /**
     * @param  array<string, mixed>  $attributes  from/to location, status, condition, bucket, quantity, note, event_id
     */
    public function record(InventoryTransactionType $type, Equipment $equipment, ?EquipmentAsset $asset = null, array $attributes = []): InventoryTransaction
    {
        $user = auth()->user();

        return InventoryTransaction::create(array_merge([
            'quantity' => 1,
        ], $attributes, [
            'type' => $type,
            'equipment_id' => $equipment->id,
            'asset_id' => $asset?->id,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? (app()->runningInConsole() && ! app()->runningUnitTests() ? 'Console' : 'System'),
            'occurred_at' => now(),
            'created_at' => now(),
        ]));
    }
}
