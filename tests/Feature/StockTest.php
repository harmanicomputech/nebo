<?php

namespace Tests\Feature;

use App\Models\AssetStatus;
use App\Models\Equipment;
use App\Models\StockLevel;
use App\Services\Inventory\AssetService;
use Tests\Concerns\InteractsWithInventory;
use Tests\TestCase;

class StockTest extends TestCase
{
    use InteractsWithInventory;

    private function qty(Equipment $item, string $code = 'MAIN', string $bucket = 'available'): int
    {
        return (int) StockLevel::where(['equipment_id' => $item->id, 'location_id' => $this->location($code)->id, 'bucket' => $bucket])->value('quantity');
    }

    private function act(Equipment $item, array $data)
    {
        return $this->actingAs($this->userWithRole('Inventory Manager'))->post("/app/inventory/equipment/{$item->id}/stock", $data + ['location_id' => $this->location()->id]);
    }

    public function test_receive_transfer_quarantine_release_and_write_off(): void
    {
        $item = $this->bulkItem();

        $this->act($item, ['action' => 'receive', 'quantity' => 100, 'purchased' => 1])->assertSessionHasNoErrors();
        $this->act($item, ['action' => 'transfer', 'quantity' => 30, 'to_location_id' => $this->location('SEC')->id])->assertSessionHasNoErrors();
        $this->act($item, ['action' => 'quarantine', 'quantity' => 5, 'note' => 'Failed test'])->assertSessionHasNoErrors();
        $this->act($item, ['action' => 'release', 'quantity' => 2])->assertSessionHasNoErrors();
        $this->act($item, ['action' => 'write_off', 'quantity' => 3, 'bucket' => 'quarantine', 'note' => 'Cut beyond repair'])->assertSessionHasNoErrors();

        $this->assertSame(67, $this->qty($item));
        $this->assertSame(0, $this->qty($item, 'MAIN', 'quarantine'));
        $this->assertSame(30, $this->qty($item, 'SEC'));
        $this->assertSame(['purchased', 'transferred', 'quarantined', 'released', 'written_off'], $item->transactions()->orderBy('id')->pluck('type')->map->value->all());

        $summary = Equipment::withAvailability()->find($item->id);
        $this->assertSame(97, $summary->availableUnits());
        $this->assertSame(97, $summary->totalUnits());
    }

    public function test_stock_can_never_go_negative(): void
    {
        $item = $this->bulkItem();
        $this->receive($item, 10);

        $this->act($item, ['action' => 'transfer', 'quantity' => 11, 'to_location_id' => $this->location('SEC')->id])->assertSessionHasErrors('quantity');
        $this->act($item, ['action' => 'quarantine', 'quantity' => 11])->assertSessionHasErrors('quantity');
        $this->act($item, ['action' => 'write_off', 'quantity' => 11, 'bucket' => 'available', 'note' => 'x'])->assertSessionHasErrors('quantity');
        $this->act($item, ['action' => 'adjust', 'quantity' => -1, 'bucket' => 'available', 'note' => 'x'])->assertSessionHasErrors('quantity');
        $this->act($item, ['action' => 'receive', 'quantity' => 0])->assertSessionHasErrors('quantity');

        $this->assertSame(10, $this->qty($item));
        $this->assertSame(0, $this->qty($item, 'SEC'));
    }

    public function test_stock_count_records_the_difference(): void
    {
        $item = $this->bulkItem();
        $this->receive($item, 40);

        $this->act($item, ['action' => 'adjust', 'quantity' => 37, 'bucket' => 'available'])->assertSessionHasErrors('note');
        $this->act($item, ['action' => 'adjust', 'quantity' => 37, 'bucket' => 'available', 'note' => 'Quarterly count'])->assertSessionHasNoErrors();

        $this->assertSame(37, $this->qty($item));
        $this->assertDatabaseHas('inventory_transactions', ['equipment_id' => $item->id, 'type' => 'adjusted', 'quantity' => -3]);
    }

    public function test_stock_actions_need_inventory_adjust_and_a_quantity_item(): void
    {
        $item = $this->bulkItem();
        $light = $this->serializedItem();

        $this->actingAs($this->userWithRole('Production Manager'))->post("/app/inventory/equipment/{$item->id}/stock", ['action' => 'receive', 'quantity' => 5, 'location_id' => $this->location()->id])->assertForbidden();
        $this->act($light, ['action' => 'receive', 'quantity' => 5])->assertForbidden();
    }

    public function test_stock_cannot_go_to_an_inactive_location(): void
    {
        $item = $this->bulkItem();
        $closed = $this->location('SEC');
        $closed->update(['is_active' => false]);

        $this->act($item, ['action' => 'receive', 'quantity' => 5, 'location_id' => $closed->id])->assertSessionHasErrors('location_id');
    }

    public function test_availability_excludes_unavailable_units(): void
    {
        $admin = $this->superAdmin();
        $item = $this->serializedItem();
        [$a, $b, $c, $d] = $this->addUnits($item, 4)->all();
        $assets = app(AssetService::class);

        $assets->changeStatus($admin, $b, AssetStatus::byCode('under_maintenance'));
        $assets->recordCondition($c, 'requires_inspection');
        $assets->changeStatus($admin, $d, AssetStatus::byCode('retired'));

        $summary = Equipment::withAvailability()->find($item->id);
        $this->assertSame(1, $summary->availableUnits());
        $this->assertSame(3, $summary->totalUnits()); // retired leaves the fleet
    }
}
