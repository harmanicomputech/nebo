<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithInventory;
use Tests\TestCase;

class InventoryDashboardAndSearchTest extends TestCase
{
    use InteractsWithInventory;

    public function test_dashboard_shows_inventory_to_inventory_users_only(): void
    {
        $item = $this->bulkItem(['name' => 'Short Supply Tape', 'low_stock_threshold' => 20]);
        $this->receive($item, 5);
        $this->addUnits($this->serializedItem(), 3);

        $this->actingAs($this->userWithRole('Inventory Manager'))->get('/app')
            ->assertOk()->assertSee('Fleet status')->assertSee('Short Supply Tape');

        $this->actingAs($this->userWithRole('Crew'))->get('/app')
            ->assertOk()->assertDontSee('Fleet status');
    }

    public function test_global_search_finds_equipment_and_assets(): void
    {
        $item = $this->serializedItem(['name' => 'Searchable Beam']);
        $this->addUnits($item, 1, ['asset_tag' => 'BEAM-777', 'serial_number' => 'SN-ABC-1']);

        $this->actingAs($this->userWithRole('Viewer'))->getJson('/app/search?q=Searchable')
            ->assertJsonPath('groups.0.label', 'Equipment');

        $this->actingAs($this->userWithRole('Viewer'))->getJson('/app/search?q=SN-ABC')
            ->assertJsonFragment(['label' => 'Assets'])->assertJsonFragment(['title' => 'BEAM-777 · Searchable Beam']);

        $this->actingAs($this->userWithRole('Crew'))->getJson('/app/search?q=Searchable')->assertJsonPath('groups', []);
    }

    public function test_sidebar_links_inventory_for_inventory_users(): void
    {
        $this->actingAs($this->userWithRole('Inventory Manager'))->get('/app')
            ->assertSee(route('app.inventory.equipment.index'))->assertSee(route('app.inventory.setup.categories'));

        $this->actingAs($this->userWithRole('Viewer'))->get('/app')
            ->assertSee(route('app.inventory.equipment.index'))->assertDontSee(route('app.inventory.setup.categories'));
    }
}
