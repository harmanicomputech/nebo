<?php

namespace Tests\Feature;

use App\Models\AssetStatus;
use App\Models\EquipmentAsset;
use App\Models\InventoryTransaction;
use App\Models\Role;
use App\Models\User;
use LogicException;
use Tests\Concerns\InteractsWithInventory;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use InteractsWithInventory;

    public function test_adding_several_units_generates_sequential_tags_and_ledger_entries(): void
    {
        $item = $this->serializedItem(['asset_prefix' => 'ML']);

        $this->actingAs($this->userWithRole('Inventory Manager'))->post("/app/inventory/equipment/{$item->id}/assets", [
            'count' => 3, 'status_id' => AssetStatus::byCode('available')->id, 'condition' => 'good', 'location_id' => $this->location()->id,
            'purchase_date' => '2026-01-10', 'purchase_cost' => '1,000,000', 'supplier' => 'Acme',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['ML-001', 'ML-002', 'ML-003'], $item->assets()->orderBy('asset_tag')->pluck('asset_tag')->all());
        $this->assertSame(100_000_000, $item->assets()->first()->purchase_cost_kobo);
        $this->assertSame(3, InventoryTransaction::where('equipment_id', $item->id)->where('type', 'purchased')->count());
        $this->assertNotNull($item->assets()->first()->qr_token);
    }

    public function test_tag_generation_skips_tags_already_used(): void
    {
        $item = $this->serializedItem(['asset_prefix' => 'ML']);
        $this->addUnits($item, 1, ['asset_tag' => 'ML-002']);

        $this->assertSame(['ML-001', 'ML-002', 'ML-003'], $this->addUnits($item, 2)->pluck('asset_tag')->push('ML-002')->sort()->values()->all());
    }

    public function test_duplicate_tags_serials_and_barcodes_are_rejected(): void
    {
        $admin = $this->superAdmin();
        $item = $this->serializedItem();
        $other = $this->serializedItem(['asset_prefix' => 'OTH']);
        $this->addUnits($item, 1, ['asset_tag' => 'TAG-1', 'serial_number' => 'SN-1', 'barcode' => 'BC-1']);
        $base = ['count' => 1, 'status_id' => AssetStatus::byCode('available')->id, 'condition' => 'good', 'location_id' => $this->location()->id];

        $this->actingAs($admin)->post("/app/inventory/equipment/{$item->id}/assets", $base + ['asset_tag' => 'tag-1', 'serial_number' => 'SN-1', 'barcode' => 'BC-1'])
            ->assertSessionHasErrors(['asset_tag', 'serial_number', 'barcode']);

        // The same serial number on a different model is fine.
        $this->actingAs($admin)->post("/app/inventory/equipment/{$other->id}/assets", $base + ['serial_number' => 'SN-1'])->assertSessionHasNoErrors();
    }

    public function test_serials_cannot_be_entered_for_several_units_at_once(): void
    {
        $item = $this->serializedItem();

        $this->actingAs($this->superAdmin())->post("/app/inventory/equipment/{$item->id}/assets", [
            'count' => 2, 'serial_number' => 'SN-X', 'status_id' => AssetStatus::byCode('available')->id, 'condition' => 'good', 'location_id' => $this->location()->id,
        ])->assertSessionHasErrors('serial_number');
    }

    public function test_quantity_items_cannot_get_units(): void
    {
        $cable = $this->bulkItem();

        $this->actingAs($this->superAdmin())->get("/app/inventory/equipment/{$cable->id}/assets/create")->assertForbidden();
    }

    public function test_status_change_is_recorded(): void
    {
        $admin = $this->superAdmin();
        $asset = $this->addUnits($this->serializedItem())->first();

        $this->actingAs($admin)->post("/app/inventory/assets/{$asset->id}/status", ['status_id' => AssetStatus::byCode('under_maintenance')->id, 'note' => 'Fan noise'])
            ->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertSame('under_maintenance', $asset->status->code);
        $this->assertFalse($asset->isAllocatable());
        $this->assertDatabaseHas('inventory_transactions', ['asset_id' => $asset->id, 'type' => 'status_changed', 'to_status_id' => $asset->status_id, 'note' => 'Fan noise', 'user_id' => $admin->id]);
    }

    public function test_allocation_engine_statuses_cannot_be_set_or_overridden_by_hand(): void
    {
        $admin = $this->superAdmin();
        $asset = $this->addUnits($this->serializedItem())->first();

        $this->actingAs($admin)->post("/app/inventory/assets/{$asset->id}/status", ['status_id' => AssetStatus::byCode('allocated')->id])
            ->assertSessionHasErrors('status_id');

        // An asset the engine has deployed can't be changed or moved by hand.
        $asset->forceFill(['status_id' => AssetStatus::byCode('deployed')->id])->saveQuietly();
        $this->actingAs($admin)->post("/app/inventory/assets/{$asset->id}/status", ['status_id' => AssetStatus::byCode('available')->id])->assertSessionHasErrors('status_id');
        $this->actingAs($admin)->post("/app/inventory/assets/{$asset->id}/move", ['location_id' => $this->location('SEC')->id])->assertSessionHasErrors('status_id');
        $this->actingAs($admin)->delete("/app/inventory/assets/{$asset->id}")->assertSessionHasErrors('status_id');

        $this->assertSame('deployed', $asset->fresh()->status->code);
        $this->assertSame($this->location()->id, $asset->fresh()->location_id);
    }

    public function test_marking_lost_or_retired_needs_archive_permission(): void
    {
        $role = Role::create(['name' => 'Storekeeper', 'guard_name' => 'web']);
        $role->syncPermissions(['inventory.view', 'inventory.update']);
        $keeper = User::factory()->create();
        $keeper->assignRole($role);
        $asset = $this->addUnits($this->serializedItem())->first();

        $this->actingAs($keeper)->post("/app/inventory/assets/{$asset->id}/status", ['status_id' => AssetStatus::byCode('lost')->id])->assertSessionHasErrors('status_id');
        $this->actingAs($keeper)->post("/app/inventory/assets/{$asset->id}/status", ['status_id' => AssetStatus::byCode('under_inspection')->id])->assertSessionHasNoErrors();

        $this->actingAs($this->userWithRole('Inventory Manager'))->post("/app/inventory/assets/{$asset->id}/status", ['status_id' => AssetStatus::byCode('lost')->id])->assertSessionHasNoErrors();
        $this->assertSame('lost', $asset->fresh()->status->code);
    }

    public function test_damaged_condition_takes_the_unit_out_of_service(): void
    {
        $asset = $this->addUnits($this->serializedItem())->first();
        $this->assertTrue($asset->fresh()->isAllocatable());

        $this->actingAs($this->userWithRole('Technician'))->get("/app/inventory/assets/{$asset->id}")->assertOk();
        $this->actingAs($this->superAdmin())->post("/app/inventory/assets/{$asset->id}/condition", ['condition' => 'damaged', 'note' => 'Cracked lens'])
            ->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertSame('damaged', $asset->condition);
        $this->assertSame('damaged', $asset->status->code);
        $this->assertFalse($asset->isAllocatable());
        $this->assertNotNull($asset->last_inspected_at);
        $this->assertDatabaseHas('inventory_transactions', ['asset_id' => $asset->id, 'type' => 'condition_changed', 'from_condition' => 'good', 'to_condition' => 'damaged']);
        $this->assertSame(0, EquipmentAsset::query()->allocatable()->whereKey($asset->id)->count());
    }

    public function test_moving_an_asset_records_from_and_to(): void
    {
        $asset = $this->addUnits($this->serializedItem())->first();

        $this->actingAs($this->superAdmin())->post("/app/inventory/assets/{$asset->id}/move", ['location_id' => $this->location('SEC')->id])->assertSessionHasNoErrors();

        $this->assertSame($this->location('SEC')->id, $asset->fresh()->location_id);
        $this->assertDatabaseHas('inventory_transactions', ['asset_id' => $asset->id, 'type' => 'transferred', 'from_location_id' => $this->location()->id, 'to_location_id' => $this->location('SEC')->id]);
    }

    public function test_archiving_an_asset_keeps_its_history(): void
    {
        $admin = $this->superAdmin();
        $asset = $this->addUnits($this->serializedItem())->first();

        $this->actingAs($admin)->delete("/app/inventory/assets/{$asset->id}")->assertRedirect();
        $this->assertSoftDeleted($asset);
        $this->assertSame(2, $asset->transactions()->count()); // added + archived
        $this->actingAs($admin)->get("/app/inventory/assets/{$asset->id}")->assertOk()->assertSee('Archived');

        $this->actingAs($admin)->post("/app/inventory/assets/{$asset->id}/restore")->assertRedirect();
        $this->assertNotSoftDeleted($asset);
    }

    public function test_qr_scan_opens_the_asset_for_signed_in_users_only(): void
    {
        $asset = $this->addUnits($this->serializedItem())->first();

        $this->get("/app/scan/{$asset->qr_token}")->assertRedirect('/login');
        $this->actingAs($this->userWithRole('Viewer'))->get("/app/scan/{$asset->qr_token}")->assertRedirect(route('app.inventory.assets.show', $asset));
        $this->actingAs($this->userWithRole('Viewer'))->get('/app/scan/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertNotFound();
    }

    public function test_labels_and_asset_page_show_a_qr_code(): void
    {
        $item = $this->serializedItem();
        $asset = $this->addUnits($item, 2)->first();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get("/app/inventory/labels?equipment={$item->id}")->assertOk()->assertSee('<svg', false)->assertSee($asset->asset_tag);
        $this->actingAs($admin)->get("/app/inventory/assets/{$asset->id}")->assertOk()->assertSee('<svg', false);
    }

    public function test_ledger_entries_are_immutable(): void
    {
        $this->addUnits($this->serializedItem());
        $entry = InventoryTransaction::firstOrFail();

        $this->expectException(LogicException::class);
        $entry->update(['note' => 'rewritten']);
    }
}
