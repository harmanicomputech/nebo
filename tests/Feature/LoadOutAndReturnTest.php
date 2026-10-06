<?php

namespace Tests\Feature;

use App\Enums\AllocationState;
use App\Enums\EventStatus;
use App\Enums\LoadStatus;
use App\Enums\StockBucket;
use App\Models\Equipment;
use App\Models\Event;
use App\Models\StockLevel;
use App\Notifications\EquipmentReturnIssues;
use App\Services\Allocation\AllocationService;
use App\Services\Allocation\LoadListService;
use App\Services\Events\EventWorkflow;
use App\Services\Events\TeamService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithEvents;
use Tests\Concerns\InteractsWithInventory;
use Tests\TestCase;

class LoadOutAndReturnTest extends TestCase
{
    use InteractsWithEvents, InteractsWithInventory;

    /**
     * An event with $units serialized units and $cables cable allocated, load list created.
     *
     * @return array{0: Event, 1: Collection, 2: Equipment}
     */
    private function preparedEvent(int $units = 3, int $cables = 20): array
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        $assets = $this->addUnits($lights, $units);
        $cable = $this->bulkItem();
        $this->receive($cable, 200);
        $event = $this->event(0, 1, ['setup_starts_at' => now()->subHour(), 'starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(6), 'breakdown_ends_at' => now()->addHours(9)]);
        app(AllocationService::class)->reserveAssets($admin, $event, $lights, $assets->pluck('id')->all());
        app(AllocationService::class)->reserveBulk($admin, $event, $cable, $cables);
        app(LoadListService::class)->sync($admin, $event);

        return [$event->fresh(), $assets, $cable];
    }

    public function test_load_list_lifecycle_and_dispatch(): void
    {
        [$event, $assets] = $this->preparedEvent();
        $im = $this->userWithRole('Inventory Manager');
        $list = $event->loadList;

        $this->assertMatchesRegularExpression('/^NEBO-LL-\d{4}-00001$/', $list->reference);
        $this->assertCount(4, $list->items);

        $this->actingAs($im)->post("/app/events/{$event->id}/load-list/dispatch")->assertSessionHasErrors('load_list');
        $this->actingAs($im)->post("/app/events/{$event->id}/load-list/items/{$list->items[0]->id}", ['status' => 'picked'])->assertRedirect();
        $this->assertSame(LoadStatus::Pending, $list->fresh()->status); // others still pending
        $this->actingAs($im)->post("/app/events/{$event->id}/load-list/advance", ['status' => 'checked']);
        $this->assertSame(LoadStatus::Checked, $list->fresh()->status);

        $this->actingAs($im)->post("/app/events/{$event->id}/load-list/dispatch")->assertSessionHasNoErrors();

        $this->assertSame(LoadStatus::Dispatched, $list->fresh()->status);
        $this->assertSame(4, $event->allocations()->where('state', AllocationState::CheckedOut)->count());
        $this->assertSame('checked_out', $assets[0]->fresh()->status->code);
        $this->assertSame(1, $assets[0]->fresh()->usage_count);
        $this->assertDatabaseHas('inventory_transactions', ['asset_id' => $assets[0]->id, 'type' => 'checked_out', 'event_id' => $event->id]);
        $this->actingAs($im)->get("/app/events/{$event->id}/load-list/print")->assertOk()->assertSee($list->reference)->assertSee($assets[0]->asset_tag);

        // Live: units are deployed. Can't complete or cancel while kit is out.
        app(EventWorkflow::class)->transition($im, $event, EventStatus::Confirmed);
        app(EventWorkflow::class)->transition($im, $event->fresh(), EventStatus::InPreparation);
        app(EventWorkflow::class)->transition($im, $event->fresh(), EventStatus::InProgress);
        $this->assertSame('deployed', $assets[0]->fresh()->status->code);
        $this->expectException(ValidationException::class);
        app(EventWorkflow::class)->transition($im, $event->fresh(), EventStatus::Completed);
    }

    public function test_crew_work_load_lists_only_for_their_events(): void
    {
        [$event] = $this->preparedEvent(1, 1);
        $crew = $this->userWithRole('Crew');
        $item = $event->loadList->items->first();

        $this->actingAs($crew)->post("/app/events/{$event->id}/load-list/items/{$item->id}", ['status' => 'picked'])->assertForbidden();

        app(TeamService::class)->assign($event, $this->staffMember('general_crew', $crew), 'general_crew');
        $this->actingAs($crew)->post("/app/events/{$event->id}/load-list/items/{$item->id}", ['status' => 'picked'])->assertRedirect();
        $this->assertSame(LoadStatus::Picked, $item->fresh()->status);
    }

    public function test_check_in_flags_missing_and_damaged_equipment(): void
    {
        Notification::fake();
        [$event, $assets, $cable] = $this->preparedEvent(3, 20);
        $admin = $this->superAdmin();
        app(LoadListService::class)->advanceAll($admin, $event->loadList, LoadStatus::Checked);
        app(LoadListService::class)->dispatch($admin, $event->loadList->fresh());
        $inventoryManager = $this->userWithRole('Inventory Manager');
        $allocations = $event->allocations()->get();
        $bulk = $allocations->firstWhere('asset_id', null);
        $byAsset = $allocations->whereNotNull('asset_id')->keyBy('asset_id');
        $tech = $this->userWithRole('Technician');
        app(TeamService::class)->assign($event, $this->staffMember('lighting_technician', $tech), 'lighting_technician');

        $this->actingAs($tech)->post("/app/events/{$event->id}/returns", [
            'location_id' => $this->location()->id,
            'lines' => [
                $byAsset[$assets[0]->id]->id => ['include' => 1, 'outcome' => 'returned'],
                $byAsset[$assets[1]->id]->id => ['include' => 1, 'outcome' => 'missing'],
                $byAsset[$assets[2]->id]->id => ['include' => 1, 'outcome' => 'damaged', 'note' => 'Cracked lens'],
                $bulk->id => ['include' => 1, 'returned' => 17, 'missing' => 2, 'damaged' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('available', $assets[0]->fresh()->status->code);
        $this->assertSame('lost', $assets[1]->fresh()->status->code);
        $this->assertSame('damaged', $assets[2]->fresh()->status->code);
        $this->assertSame('damaged', $assets[2]->fresh()->condition);
        $this->assertSame(0, $event->allocations()->where('state', AllocationState::CheckedOut)->count());
        // 200 owned - 2 written off - 1 quarantined.
        $this->assertSame(197, (int) StockLevel::where(['equipment_id' => $cable->id, 'bucket' => StockBucket::Available->value])->sum('quantity'));
        $this->assertSame(1, (int) StockLevel::where(['equipment_id' => $cable->id, 'bucket' => StockBucket::Quarantine->value])->sum('quantity'));

        $check = $event->returnChecks()->first();
        $this->assertSame([18, 3, 2], [$check->returned_count, $check->missing_count, $check->damaged_count]);
        $this->assertDatabaseHas('inventory_transactions', ['asset_id' => $assets[1]->id, 'type' => 'lost', 'event_id' => $event->id]);
        Notification::assertSentTo($inventoryManager, EquipmentReturnIssues::class);

        // With everything back, the event can be completed.
        app(EventWorkflow::class)->transition($admin, $event->fresh(), EventStatus::Confirmed);
        app(EventWorkflow::class)->transition($admin, $event->fresh(), EventStatus::InPreparation);
        app(EventWorkflow::class)->transition($admin, $event->fresh(), EventStatus::InProgress);
        app(EventWorkflow::class)->transition($admin, $event->fresh(), EventStatus::Completed);
        $this->assertSame(EventStatus::Completed, $event->fresh()->status);
    }

    public function test_twenty_out_nineteen_back_one_missing_is_flagged(): void
    {
        Notification::fake();
        [$event, $assets] = $this->preparedEvent(20, 1);
        $admin = $this->superAdmin();
        app(LoadListService::class)->advanceAll($admin, $event->loadList, LoadStatus::Checked);
        app(LoadListService::class)->dispatch($admin, $event->loadList->fresh());

        $lines = $event->allocations()->whereNotNull('asset_id')->get()->mapWithKeys(fn ($a, $i) => [$a->id => ['include' => 1, 'outcome' => $i === 0 ? 'missing' : 'returned']])->all();
        $lines[$event->allocations()->whereNull('asset_id')->value('id')] = ['include' => 1, 'returned' => 1, 'missing' => 0, 'damaged' => 0];

        $this->actingAs($admin)->post("/app/events/{$event->id}/returns", ['location_id' => $this->location()->id, 'lines' => $lines])
            ->assertSessionHas('error', 'Checked in: 20 returned, 1 missing, 0 damaged or needing attention.');
        $this->assertSame(1, $assets->filter(fn ($a) => $a->fresh()->status->code === 'lost')->count());
    }

    public function test_bulk_return_must_account_for_every_unit(): void
    {
        [$event] = $this->preparedEvent(1, 20);
        $admin = $this->superAdmin();
        app(LoadListService::class)->advanceAll($admin, $event->loadList, LoadStatus::Checked);
        app(LoadListService::class)->dispatch($admin, $event->loadList->fresh());
        $bulk = $event->allocations()->whereNull('asset_id')->first();

        $this->actingAs($admin)->post("/app/events/{$event->id}/returns", ['location_id' => $this->location()->id, 'lines' => [$bulk->id => ['include' => 1, 'returned' => 15, 'missing' => 1, 'damaged' => 0]]])
            ->assertSessionHasErrors("lines.{$bulk->id}");
        $this->assertSame(AllocationState::CheckedOut, $bulk->fresh()->state);
    }

    public function test_a_returned_unit_already_booked_for_a_later_event_stays_allocated(): void
    {
        $admin = $this->superAdmin();
        [$event, $assets] = $this->preparedEvent(1, 1);
        $later = $this->event(15);
        app(AllocationService::class)->reserveAssets($admin, $later, $assets[0]->equipment, [$assets[0]->id]);
        app(LoadListService::class)->advanceAll($admin, $event->loadList, LoadStatus::Checked);
        app(LoadListService::class)->dispatch($admin, $event->loadList->fresh());
        $allocation = $event->allocations()->where('asset_id', $assets[0]->id)->first();

        $this->actingAs($admin)->post("/app/events/{$event->id}/returns", ['location_id' => $this->location()->id, 'lines' => [$allocation->id => ['include' => 1, 'outcome' => 'returned']]]);

        $this->assertSame('allocated', $assets[0]->fresh()->status->code);
    }

    public function test_overdue_returns_show_on_the_dashboard(): void
    {
        $admin = $this->superAdmin();
        [$event] = $this->preparedEvent(1, 1);
        app(LoadListService::class)->advanceAll($admin, $event->loadList, LoadStatus::Checked);
        app(LoadListService::class)->dispatch($admin, $event->loadList->fresh());
        $event->allocations()->update(['hold_ends_at' => now()->subHour()]);

        $this->actingAs($this->userWithRole('Operations Manager'))->get('/app')->assertSee('Overdue returns')->assertSee($event->name);
    }
}
