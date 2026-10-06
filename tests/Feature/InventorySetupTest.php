<?php

namespace Tests\Feature;

use App\Models\AssetStatus;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\Lookup;
use App\Support\Lookups;
use Tests\Concerns\InteractsWithInventory;
use Tests\TestCase;

class InventorySetupTest extends TestCase
{
    use InteractsWithInventory;

    public function test_reference_data_from_the_brief_is_seeded(): void
    {
        foreach (['Moving Lights', 'Conventionals and LEDs', 'Followspots', 'Consoles', 'Special FX', 'Distro', 'Trussing', 'Dimmers', 'Rigging', 'Cables', 'Road Cases', 'Communications'] as $name) {
            $this->assertDatabaseHas('equipment_categories', ['name' => $name, 'parent_id' => null]);
        }
        $this->assertSame(14, AssetStatus::count());
        $this->assertSame(['available'], AssetStatus::where('is_allocatable', true)->pluck('code')->all());
        $this->assertDatabaseHas('locations', ['code' => 'MAIN']);
    }

    public function test_categories_and_subcategories(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/app/inventory/setup/categories', ['name' => 'Staging'])->assertSessionHasNoErrors();
        $staging = EquipmentCategory::where('name', 'Staging')->firstOrFail();
        $this->actingAs($admin)->post('/app/inventory/setup/categories', ['name' => 'Decks', 'parent_id' => $staging->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('equipment_categories', ['name' => 'Decks', 'parent_id' => $staging->id, 'slug' => 'staging-decks']);

        // Same name under the same parent is a duplicate; subcategories can't nest deeper.
        $this->actingAs($admin)->post('/app/inventory/setup/categories', ['name' => 'Decks', 'parent_id' => $staging->id])->assertSessionHasErrors('name');
        $decks = EquipmentCategory::where('name', 'Decks')->first();
        $this->actingAs($admin)->post('/app/inventory/setup/categories', ['name' => 'Too deep', 'parent_id' => $decks->id])->assertSessionHasErrors('parent_id');

        // A parent with live subcategories can't be archived.
        $this->actingAs($admin)->delete("/app/inventory/setup/categories/{$staging->id}")->assertSessionHasErrors('category');
        $this->actingAs($admin)->delete("/app/inventory/setup/categories/{$decks->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted($decks);
        $this->assertArrayNotHasKey($decks->id, EquipmentCategory::options());
    }

    public function test_locations_can_only_be_archived_when_empty(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post('/app/inventory/setup/locations', ['name' => 'Abuja Warehouse', 'code' => 'abj-wh', 'type' => 'warehouse'])->assertSessionHasNoErrors();
        $abuja = Location::where('code', 'ABJ-WH')->firstOrFail();

        $item = $this->bulkItem();
        $this->receive($item, 5, 'ABJ-WH');

        $this->actingAs($admin)->delete("/app/inventory/setup/locations/{$abuja->id}")->assertSessionHasErrors('location');
        $this->assertNotSoftDeleted($abuja);

        $this->actingAs($admin)->post('/app/inventory/setup/locations', ['name' => 'Dup', 'code' => 'ABJ-WH', 'type' => 'warehouse'])->assertSessionHasErrors('code');
    }

    public function test_custom_statuses_are_manual_and_system_behaviour_is_fixed(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post('/app/inventory/setup/statuses', ['label' => 'On Loan', 'group' => 'unavailable', 'tone' => 'info', 'is_allocatable' => 1])->assertSessionHasNoErrors();
        $loan = AssetStatus::where('code', 'on_loan')->firstOrFail();
        $this->assertTrue($loan->is_manual);
        $this->assertFalse($loan->is_allocatable); // only "available" statuses may be allocatable

        $available = AssetStatus::byCode('available');
        $this->actingAs($admin)->put("/app/inventory/setup/statuses/{$available->id}", ['label' => 'Ready', 'tone' => 'success', 'is_active' => 0])->assertSessionHasNoErrors();
        $available->refresh();
        $this->assertSame('Ready', $available->label);
        $this->assertTrue($available->is_active);
        $this->assertTrue($available->is_allocatable);
    }

    public function test_option_lists_can_be_extended_renamed_and_switched_off(): void
    {
        $admin = $this->superAdmin();
        $asset = $this->addUnits($this->serializedItem(), 1, ['condition' => 'fair'])->first();

        $this->actingAs($admin)->post('/app/settings/options/condition', ['label' => 'Needs Cleaning'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lookups', ['group' => 'condition', 'key' => 'needs_cleaning']);

        $fair = Lookup::where(['group' => 'condition', 'key' => 'fair'])->first();
        $this->actingAs($admin)->put("/app/settings/options/item/{$fair->id}", ['label' => 'Fair (usable)', 'is_active' => 0])->assertSessionHasNoErrors();

        $lookups = app(Lookups::class);
        $lookups->flush();
        $this->assertArrayNotHasKey('fair', $lookups->options('condition'));
        $this->assertArrayHasKey('fair', $lookups->options('condition', 'fair')); // kept for records that use it
        $this->assertSame('Fair (usable)', $asset->fresh()->conditionLabel());

        $this->actingAs($admin)->get('/app/settings/options/nonsense')->assertNotFound();
    }

    public function test_setup_needs_inventory_configure(): void
    {
        $pm = $this->userWithRole('Production Manager');

        $this->actingAs($pm)->get('/app/inventory/setup/categories')->assertForbidden();
        $this->actingAs($pm)->post('/app/inventory/setup/locations', ['name' => 'X', 'code' => 'X', 'type' => 'warehouse'])->assertForbidden();
        $this->actingAs($pm)->post('/app/settings/options/unit', ['label' => 'Crate'])->assertForbidden();
        $this->actingAs($this->userWithRole('Inventory Manager'))->get('/app/inventory/setup/statuses')->assertOk();
    }
}
