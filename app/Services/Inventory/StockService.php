<?php

namespace App\Services\Inventory;

use App\Enums\InventoryTransactionType as T;
use App\Enums\StockBucket;
use App\Models\Equipment;
use App\Models\Location;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quantity-tracked (bulk) stock. Every operation locks the stock rows it
 * touches, refuses to go below zero, and writes the ledger.
 */
class StockService
{
    public function __construct(private InventoryLedger $ledger) {}

    public function receive(Equipment $equipment, Location $location, int $quantity, bool $purchased = false, ?string $note = null): void
    {
        $this->guard($equipment, $quantity, $location);

        DB::transaction(function () use ($equipment, $location, $quantity, $purchased, $note) {
            $level = $this->level($equipment, $location, StockBucket::Available);
            $level->update(['quantity' => $level->quantity + $quantity]);

            $this->ledger->record($purchased ? T::Purchased : T::Added, $equipment, null, [
                'quantity' => $quantity, 'to_location_id' => $location->id, 'to_bucket' => StockBucket::Available->value, 'note' => $note,
            ]);
        });
    }

    public function transfer(Equipment $equipment, Location $from, Location $to, int $quantity, ?string $note = null): void
    {
        $this->guard($equipment, $quantity, $to);

        if ($from->is($to)) {
            throw ValidationException::withMessages(['to_location_id' => 'Choose a different destination.']);
        }

        DB::transaction(function () use ($equipment, $from, $to, $quantity, $note) {
            // Lock in a stable order so two opposite transfers can't deadlock.
            [$first, $second] = $from->id < $to->id ? [$from, $to] : [$to, $from];
            $levels = [
                $first->id => $this->level($equipment, $first, StockBucket::Available),
                $second->id => $this->level($equipment, $second, StockBucket::Available),
            ];

            $this->take($levels[$from->id], $quantity, $from);
            $levels[$to->id]->update(['quantity' => $levels[$to->id]->quantity + $quantity]);

            $this->ledger->record(T::Transferred, $equipment, null, [
                'quantity' => $quantity, 'from_location_id' => $from->id, 'to_location_id' => $to->id,
                'from_bucket' => StockBucket::Available->value, 'to_bucket' => StockBucket::Available->value, 'note' => $note,
            ]);
        });
    }

    /** Moves units between available and quarantine at one location. */
    public function moveBucket(Equipment $equipment, Location $location, StockBucket $from, StockBucket $to, int $quantity, ?string $note = null): void
    {
        $this->guard($equipment, $quantity);

        if ($from === $to) {
            return;
        }

        DB::transaction(function () use ($equipment, $location, $from, $to, $quantity, $note) {
            $source = $this->level($equipment, $location, $from);
            $target = $this->level($equipment, $location, $to);

            $this->take($source, $quantity, $location);
            $target->update(['quantity' => $target->quantity + $quantity]);

            $this->ledger->record($to === StockBucket::Quarantine ? T::Quarantined : T::Released, $equipment, null, [
                'quantity' => $quantity, 'from_location_id' => $location->id, 'to_location_id' => $location->id,
                'from_bucket' => $from->value, 'to_bucket' => $to->value, 'note' => $note,
            ]);
        });
    }

    /** Removes lost or scrapped units for good. */
    public function writeOff(Equipment $equipment, Location $location, StockBucket $bucket, int $quantity, string $note): void
    {
        $this->guard($equipment, $quantity);

        DB::transaction(function () use ($equipment, $location, $bucket, $quantity, $note) {
            $this->take($this->level($equipment, $location, $bucket), $quantity, $location);

            $this->ledger->record(T::WrittenOff, $equipment, null, [
                'quantity' => -$quantity, 'from_location_id' => $location->id, 'from_bucket' => $bucket->value, 'note' => $note,
            ]);
        });
    }

    /** Sets a counted quantity (stock-take correction). The ledger stores the difference. */
    public function adjust(Equipment $equipment, Location $location, StockBucket $bucket, int $counted, string $note): void
    {
        $this->guard($equipment, max(1, $counted), $location);

        if ($counted < 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity cannot be negative.']);
        }

        DB::transaction(function () use ($equipment, $location, $bucket, $counted, $note) {
            $level = $this->level($equipment, $location, $bucket);
            $difference = $counted - $level->quantity;

            if ($difference === 0) {
                return;
            }

            $level->update(['quantity' => $counted]);

            $this->ledger->record(T::Adjusted, $equipment, null, [
                'quantity' => $difference, 'to_location_id' => $location->id, 'to_bucket' => $bucket->value,
                'note' => $note.' (was '.($counted - $difference).', counted '.$counted.')',
            ]);
        });
    }

    private function guard(Equipment $equipment, int $quantity, ?Location $destination = null): void
    {
        if ($equipment->isSerialized()) {
            throw ValidationException::withMessages(['equipment' => 'This item is serialized. Add or move individual assets instead.']);
        }

        if ($equipment->trashed() || ! $equipment->is_active) {
            throw ValidationException::withMessages(['equipment' => 'This item is archived or inactive.']);
        }

        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Enter a quantity of at least 1.']);
        }

        if ($destination && (! $destination->is_active || $destination->trashed())) {
            throw ValidationException::withMessages(['location_id' => 'Choose an active location.']);
        }
    }

    private function level(Equipment $equipment, Location $location, StockBucket $bucket): StockLevel
    {
        $keys = ['equipment_id' => $equipment->id, 'location_id' => $location->id, 'bucket' => $bucket->value];

        StockLevel::query()->firstOrCreate($keys, ['quantity' => 0]);

        return StockLevel::query()->where($keys)->lockForUpdate()->firstOrFail();
    }

    private function take(StockLevel $level, int $quantity, Location $location): void
    {
        if ($level->quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$level->quantity} {$level->bucket->label()} at {$location->name}; you can't remove {$quantity}.",
            ]);
        }

        $level->update(['quantity' => $level->quantity - $quantity]);
    }
}
