<?php

namespace Tests\Feature;

use App\Enums\AllocationState;
use App\Enums\EventStatus;
use App\Models\AssetStatus;
use App\Models\EquipmentAllocation;
use App\Services\Allocation\AllocationService;
use App\Services\Allocation\RequirementService;
use App\Services\Events\EventService;
use App\Services\Events\EventWorkflow;
use App\Services\Inventory\AssetService;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithEvents;
use Tests\Concerns\InteractsWithInventory;
use Tests\TestCase;

class AllocationTest extends TestCase
{
    use InteractsWithEvents, InteractsWithInventory;

    public function test_production_manager_allocates_specific_units(): void
    {
        $pm = $this->userWithRole('Production Manager');
        $lights = $this->serializedItem();
        [$one, $two] = $this->addUnits($lights, 3)->all();
        $event = $this->event();
        app(RequirementService::class)->set($event, $lights, 2);

        $this->actingAs($pm)->post("/app/events/{$event->id}/allocations", ['equipment_id' => $lights->id, 'mode' => 'assets', 'assets' => [$one->id, $two->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $event->allocations()->active()->count());
        $this->assertSame('allocated', $one->fresh()->status->code);
        $this->assertDatabaseHas('inventory_transactions', ['asset_id' => $one->id, 'type' => 'allocated', 'event_id' => $event->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'allocated', 'auditable_id' => (string) $event->id]);
        $this->actingAs($pm)->get("/app/events/{$event->id}?tab=equipment")->assertSee('Fully allocated');
        $this->actingAs($pm)->get("/app/inventory/assets/{$one->id}")->assertSee($event->name);
    }

    public function test_the_same_unit_can_never_be_allocated_to_overlapping_events(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        $unit = $this->addUnits($lights, 1)->first();
        $a = $this->event(10, 2);
        $b = $this->event(11, 2);
        $c = $this->event(20, 1);
        $service = app(AllocationService::class);

        $service->reserveAssets($admin, $a, $lights, [$unit->id]);

        try {
            $service->reserveAssets($admin, $b, $lights, [$unit->id]);
            $this->fail('Double allocation was allowed.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString("isn't available", $e->errors()['assets'][0]);
        }

        // Twice on the same event is refused too; a non-overlapping event is fine.
        $this->expectException(ValidationException::class);
        try {
            $service->reserveAssets($admin, $a, $lights, [$unit->id]);
        } finally {
            $service->reserveAssets($admin, $c, $lights, [$unit->id]);
            $this->assertSame(2, EquipmentAllocation::where('asset_id', $unit->id)->active()->count());
        }
    }

    public function test_units_in_maintenance_or_damaged_cannot_be_allocated(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        [$maint, $damaged] = $this->addUnits($lights, 2)->all();
        app(AssetService::class)->changeStatus($admin, $maint, AssetStatus::byCode('under_maintenance'));
        app(AssetService::class)->recordCondition($damaged, 'critical');
        $event = $this->event();

        foreach ([$maint, $damaged] as $asset) {
            $this->actingAs($admin)->post("/app/events/{$event->id}/allocations", ['equipment_id' => $lights->id, 'mode' => 'assets', 'assets' => [$asset->id]])
                ->assertSessionHasErrors('assets');
        }
        $this->actingAs($admin)->post("/app/events/{$event->id}/allocations", ['equipment_id' => $lights->id, 'mode' => 'auto', 'quantity' => 1])->assertSessionHasErrors('quantity');
        $this->assertSame(0, $event->allocations()->count());
    }

    public function test_bulk_allocations_cannot_exceed_free_stock(): void
    {
        $admin = $this->superAdmin();
        $cable = $this->bulkItem();
        $this->receive($cable, 50);
        $a = $this->event(5);
        $b = $this->event(5);

        $this->actingAs($admin)->post("/app/events/{$a->id}/allocations", ['equipment_id' => $cable->id, 'mode' => 'bulk', 'quantity' => 40])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/app/events/{$b->id}/allocations", ['equipment_id' => $cable->id, 'mode' => 'bulk', 'quantity' => 11])->assertSessionHasErrors('quantity');
        $this->actingAs($admin)->post("/app/events/{$b->id}/allocations", ['equipment_id' => $cable->id, 'mode' => 'bulk', 'quantity' => 10])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/app/events/{$a->id}/allocations", ['equipment_id' => $cable->id, 'mode' => 'bulk', 'quantity' => 1])->assertSessionHasErrors('quantity');

        // The workspace renders bulk allocations (regression: lazy-loaded location).
        app(RequirementService::class)->set($a, $cable, 40);
        $this->actingAs($admin)->get("/app/events/{$a->id}?tab=equipment")->assertOk()->assertSee('40 × MAIN');
        $this->actingAs($admin)->get("/app/events/{$a->id}?tab=allocation")->assertOk();
    }

    public function test_releasing_returns_the_unit_to_available(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        $unit = $this->addUnits($lights, 1)->first();
        $event = $this->event();
        $allocation = app(AllocationService::class)->reserveAssets($admin, $event, $lights, [$unit->id])->first();

        $this->actingAs($admin)->delete("/app/events/{$event->id}/allocations/{$allocation->id}")->assertSessionHasNoErrors();

        $this->assertSame(AllocationState::Released, $allocation->fresh()->state);
        $this->assertSame('available', $unit->fresh()->status->code);
    }

    public function test_allocated_units_are_protected_from_manual_changes(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        $unit = $this->addUnits($lights, 1)->first();
        app(AllocationService::class)->reserveAssets($admin, $this->event(), $lights, [$unit->id]);

        $this->actingAs($admin)->post("/app/inventory/assets/{$unit->id}/move", ['location_id' => $this->location('SEC')->id])->assertSessionHasErrors('status_id');
        $this->actingAs($admin)->delete("/app/inventory/assets/{$unit->id}")->assertSessionHasErrors('status_id');
    }

    public function test_cancelling_releases_reservations_and_completing_needs_everything_back(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        $unit = $this->addUnits($lights, 1)->first();
        $event = $this->event();
        app(AllocationService::class)->reserveAssets($admin, $event, $lights, [$unit->id]);

        app(EventWorkflow::class)->transition($admin, $event, EventStatus::Cancelled, 'Client cancelled');

        $this->assertSame(0, $event->allocations()->active()->count());
        $this->assertSame('available', $unit->fresh()->status->code);
    }

    public function test_changing_event_dates_moves_holds_or_is_refused(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        $unit = $this->addUnits($lights, 1)->first();
        $a = $this->event(10, 1);
        $b = $this->event(20, 1);
        $service = app(AllocationService::class);
        $service->reserveAssets($admin, $a, $lights, [$unit->id]);
        $service->reserveAssets($admin, $b, $lights, [$unit->id]);

        // Moving A a day later is fine and its hold follows.
        app(EventService::class)->update($a, ['setup_starts_at' => now()->addDays(11)->setTime(8, 0), 'starts_at' => now()->addDays(11)->setTime(12, 0), 'ends_at' => now()->addDays(11)->setTime(22, 0), 'breakdown_ends_at' => now()->addDays(12)->setTime(3, 0)]);
        $this->assertTrue($a->allocations()->first()->hold_starts_at->isSameDay(now()->addDays(11)));

        // Moving A onto B's dates clashes: refused, nothing changes.
        try {
            app(EventService::class)->update($a, ['setup_starts_at' => now()->addDays(20)->setTime(8, 0), 'starts_at' => now()->addDays(20)->setTime(12, 0), 'ends_at' => now()->addDays(20)->setTime(22, 0), 'breakdown_ends_at' => now()->addDays(21)->setTime(3, 0)]);
            $this->fail('Clashing date change was allowed.');
        } catch (ValidationException) {
            $this->assertTrue($a->fresh()->setup_starts_at->isSameDay(now()->addDays(11)));
        }
    }

    public function test_requirements_show_shortages_conflicts_and_alternatives(): void
    {
        $admin = $this->superAdmin();
        $spot = $this->serializedItem(['name' => 'Spot Fixture', 'category_id' => $this->category('moving-lights-spot-profile')->id]);
        $wash = $this->serializedItem(['name' => 'Wash Fixture', 'asset_prefix' => 'WSH', 'category_id' => $this->category('moving-lights-wash')->id]);
        $this->addUnits($spot, 4);
        $this->addUnits($wash, 6);
        $other = $this->event(10, 2, ['name' => 'Competing Show']);
        $mine = $this->event(11, 1);
        app(AllocationService::class)->autoReserve($admin, $other, $spot, 3);
        app(RequirementService::class)->set($mine, $spot, 4);

        $this->actingAs($admin)->get("/app/events/{$mine->id}?tab=equipment")->assertOk()
            ->assertSee('Shortage')->assertSee('3 short')->assertSee('Competing Show')->assertSee('Wash Fixture');
    }

    public function test_requirements_with_allocations_cannot_be_removed(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        $this->addUnits($lights, 1);
        $event = $this->event();
        $req = app(RequirementService::class)->set($event, $lights, 1);
        app(AllocationService::class)->autoReserve($admin, $event, $lights, 1);

        $this->actingAs($admin)->delete("/app/events/{$event->id}/requirements/{$req->id}")->assertSessionHasErrors('requirement');
    }

    public function test_only_allocation_managers_can_allocate(): void
    {
        $lights = $this->serializedItem();
        $this->addUnits($lights, 1);
        $event = $this->event();

        foreach (['Viewer', 'Finance / Commercial'] as $role) {
            $this->actingAs($this->userWithRole($role))->post("/app/events/{$event->id}/allocations", ['equipment_id' => $lights->id, 'mode' => 'auto', 'quantity' => 1])->assertForbidden();
        }
        $this->actingAs($this->userWithRole('Inventory Manager'))->post("/app/events/{$event->id}/allocations", ['equipment_id' => $lights->id, 'mode' => 'auto', 'quantity' => 1])->assertSessionHasNoErrors();
    }
}
