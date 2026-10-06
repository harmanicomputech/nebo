<?php

namespace Tests\Feature;

use App\Models\AssetStatus;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\Inventory\AssetService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithInventory;
use Tests\TestCase;

class EquipmentCatalogueTest extends TestCase
{
    use InteractsWithInventory;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category()->id,
            'name' => 'Robe MegaPointe',
            'sku' => 'ml-megapointe',
            'manufacturer' => 'Robe',
            'model' => 'MegaPointe',
            'tracking_mode' => 'serialized',
            'unit' => 'unit',
            'asset_prefix' => 'ml-',
            'replacement_value' => '9,500,000',
            'low_stock_threshold' => 4,
        ], $overrides);
    }

    public function test_inventory_manager_adds_equipment(): void
    {
        $this->actingAs($this->userWithRole('Inventory Manager'))
            ->post('/app/inventory/equipment', $this->payload())
            ->assertSessionHasNoErrors()->assertRedirect();

        $item = Equipment::where('sku', 'ML-MEGAPOINTE')->firstOrFail();
        $this->assertSame('ML', $item->asset_prefix);
        $this->assertSame(950_000_000, $item->replacement_value_kobo);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => 'Equipment', 'auditable_id' => (string) $item->id]);
    }

    public function test_sku_must_be_unique_and_category_must_exist(): void
    {
        $this->serializedItem(['sku' => 'DUP-1']);

        $this->actingAs($this->superAdmin())->post('/app/inventory/equipment', $this->payload(['sku' => 'dup-1', 'category_id' => 99999]))
            ->assertSessionHasErrors(['sku', 'category_id']);
    }

    public function test_quantity_items_cannot_have_an_asset_prefix(): void
    {
        $this->actingAs($this->superAdmin())->post('/app/inventory/equipment', $this->payload(['tracking_mode' => 'bulk', 'unit' => 'piece']))
            ->assertSessionHasErrors('asset_prefix');
    }

    public function test_quantity_items_can_be_edited(): void
    {
        $cable = $this->bulkItem(['sku' => 'CBL-EDIT']);

        $this->actingAs($this->superAdmin())->put("/app/inventory/equipment/{$cable->id}", $this->payload([
            'sku' => 'CBL-EDIT', 'name' => 'Renamed Cable', 'tracking_mode' => 'bulk', 'unit' => 'piece', 'asset_prefix' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Renamed Cable', $cable->fresh()->name);
    }

    public function test_tracking_mode_cannot_change_once_units_exist(): void
    {
        $item = $this->serializedItem();
        $this->addUnits($item);

        $this->actingAs($this->superAdmin())->put("/app/inventory/equipment/{$item->id}", $this->payload(['sku' => $item->sku, 'tracking_mode' => 'bulk', 'unit' => 'piece', 'asset_prefix' => null]))
            ->assertSessionHasErrors('tracking_mode');

        $this->assertTrue($item->fresh()->isSerialized());
    }

    public function test_costs_are_ignored_without_the_costs_permission(): void
    {
        $role = Role::create(['name' => 'Cataloguer', 'guard_name' => 'web']);
        $role->syncPermissions(['inventory.view', 'inventory.create', 'dashboard.view']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->post('/app/inventory/equipment', $this->payload())->assertSessionHasNoErrors();

        $this->assertNull(Equipment::where('sku', 'ML-MEGAPOINTE')->value('replacement_value_kobo'));
        $this->actingAs($user)->get('/app/inventory/equipment/create')->assertDontSee('Replacement value');
    }

    public function test_images_are_re_encoded_stored_privately_and_served_only_to_inventory_users(): void
    {
        Storage::fake('local');
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/app/inventory/equipment', $this->payload([
            'image' => UploadedFile::fake()->image('light.jpg', 2400, 1200),
        ]))->assertSessionHasNoErrors();

        $item = Equipment::where('sku', 'ML-MEGAPOINTE')->firstOrFail();
        Storage::disk('local')->assertExists($item->image_path);
        $this->assertStringStartsWith('images/equipment/', $item->image_path);
        [$width] = getimagesizefromstring(Storage::disk('local')->get($item->image_path));
        $this->assertSame(1600, $width);

        $this->actingAs($admin)->get("/app/inventory/equipment/{$item->id}/image")->assertOk();
        $this->actingAs($this->userWithRole('Crew'))->get("/app/inventory/equipment/{$item->id}/image")->assertForbidden();
        $this->post('/logout');
        $this->get("/app/inventory/equipment/{$item->id}/image")->assertRedirect('/login');
    }

    public function test_files_that_are_not_images_are_rejected(): void
    {
        Storage::fake('local');

        $this->actingAs($this->superAdmin())->post('/app/inventory/equipment', $this->payload([
            'image' => UploadedFile::fake()->createWithContent('evil.png', '<?php echo "hi"; ?>'),
        ]))->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('equipment', ['sku' => 'ML-MEGAPOINTE']);
    }

    public function test_archiving_needs_the_fleet_retired_first_and_keeps_history(): void
    {
        $admin = $this->superAdmin();
        $item = $this->serializedItem();
        $asset = $this->addUnits($item)->first();

        $this->actingAs($admin)->delete("/app/inventory/equipment/{$item->id}")->assertSessionHasErrors('equipment');
        $this->assertNotSoftDeleted($item);

        app(AssetService::class)->changeStatus($admin, $asset->fresh(), AssetStatus::byCode('retired'));
        $this->actingAs($admin)->delete("/app/inventory/equipment/{$item->id}")->assertRedirect(route('app.inventory.equipment.index'));
        $this->assertSoftDeleted($item);
        $this->assertGreaterThan(0, $item->transactions()->count());

        $this->actingAs($admin)->get("/app/inventory/equipment/{$item->id}")->assertOk()->assertSee('Archived');
        $this->actingAs($admin)->post("/app/inventory/equipment/{$item->id}/restore")->assertRedirect();
        $this->assertNotSoftDeleted($item);
    }

    public function test_list_filters_search_and_views(): void
    {
        $admin = $this->superAdmin();
        $light = $this->serializedItem(['name' => 'Searchable Spot', 'low_stock_threshold' => 5]);
        $this->addUnits($light, 2);
        $cable = $this->bulkItem(['name' => 'Plenty of Cable']);
        $this->receive($cable, 50);
        $sub = EquipmentCategory::where('slug', 'moving-lights-wash')->first();
        $this->serializedItem(['name' => 'Wash In Subcategory', 'category_id' => $sub->id, 'asset_prefix' => 'WSH']);

        $this->actingAs($admin)->get('/app/inventory/equipment?availability=low')->assertSee('Searchable Spot')->assertDontSee('Plenty of Cable');
        $this->actingAs($admin)->get('/app/inventory/equipment?mode=bulk')->assertSee('Plenty of Cable')->assertDontSee('Searchable Spot');
        $this->actingAs($admin)->get('/app/inventory/equipment?q=TML-001')->assertSee('Searchable Spot')->assertDontSee('Plenty of Cable');
        // A parent category includes its subcategories.
        $this->actingAs($admin)->get('/app/inventory/equipment?category='.$this->category()->id)->assertSee('Wash In Subcategory');
        $this->actingAs($admin)->get('/app/inventory/equipment?view=grid&availability=available')->assertOk()->assertSee('Plenty of Cable')->assertDontSee('Wash In Subcategory');
        $this->actingAs($admin)->get('/app/inventory/equipment?q=nothing-matches')->assertSee('No equipment found');
    }

    public function test_viewers_can_browse_but_not_change_and_crew_cannot_see_inventory(): void
    {
        $item = $this->serializedItem();
        $viewer = $this->userWithRole('Viewer');

        $this->actingAs($viewer)->get('/app/inventory/equipment')->assertOk()->assertSee($item->name)->assertDontSee('Add equipment');
        $this->actingAs($viewer)->get("/app/inventory/equipment/{$item->id}")->assertOk();
        $this->actingAs($viewer)->get('/app/inventory/equipment/create')->assertForbidden();
        $this->actingAs($viewer)->post('/app/inventory/equipment', $this->payload())->assertForbidden();
        $this->actingAs($viewer)->delete("/app/inventory/equipment/{$item->id}")->assertForbidden();

        $crew = $this->userWithRole('Crew');
        $this->actingAs($crew)->get('/app/inventory/equipment')->assertForbidden();
        $this->actingAs($crew)->get('/app/inventory/assets')->assertForbidden();
        $this->actingAs($crew)->get('/app/inventory/movements')->assertForbidden();
    }
}
