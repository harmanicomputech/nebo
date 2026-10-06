<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\LoadStatus;
use App\Enums\TripDirection;
use App\Enums\TripStatus;
use App\Models\Event;
use App\Models\LogisticsTrip;
use App\Models\Vehicle;
use App\Notifications\TripAssigned;
use App\Services\Allocation\AllocationService;
use App\Services\Allocation\LoadListService;
use App\Services\Events\EventWorkflow;
use App\Services\Logistics\TripService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithEvents;
use Tests\Concerns\InteractsWithInventory;
use Tests\TestCase;

class LogisticsTest extends TestCase
{
    use InteractsWithEvents, InteractsWithInventory;

    private function vehicle(array $attributes = []): Vehicle
    {
        return Vehicle::create($attributes + ['name' => 'Truck '.uniqid(), 'registration' => strtoupper(uniqid('LSD')), 'type' => 'truck', 'status' => 'active']);
    }

    /** @param array<string, mixed> $overrides */
    private function tripData(Event $event, array $overrides = []): array
    {
        return $overrides + [
            'direction' => 'outbound', 'origin' => 'Main Warehouse', 'destination' => $event->venue,
            'departs_at' => CarbonImmutable::parse($event->setup_starts_at)->subHours(3), 'arrives_at' => CarbonImmutable::parse($event->setup_starts_at),
            'crew' => [], 'items' => [],
        ];
    }

    /** An event happening now with 2 units and 10 cables allocated and dispatched. */
    private function dispatchedEvent(): array
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        $units = $this->addUnits($lights, 2);
        $cable = $this->bulkItem();
        $this->receive($cable, 50);
        $event = $this->event(0, 1, ['setup_starts_at' => now()->addHours(2), 'starts_at' => now()->addHours(5), 'ends_at' => now()->addHours(9), 'breakdown_ends_at' => now()->addHours(12)]);
        app(AllocationService::class)->reserveAssets($admin, $event, $lights, $units->pluck('id')->all());
        app(AllocationService::class)->reserveBulk($admin, $event, $cable, 10);
        $list = app(LoadListService::class)->sync($admin, $event);
        app(LoadListService::class)->advanceAll($admin, $list, LoadStatus::Checked);
        app(LoadListService::class)->dispatch($admin, $list->fresh());

        return [$event->fresh(), $units, $admin];
    }

    public function test_manager_plans_a_trip_from_the_event(): void
    {
        Notification::fake();
        $manager = $this->userWithRole('Operations Manager');
        $driverUser = $this->userWithRole('Crew');
        $driver = $this->staffMember('driver', $driverUser);
        $truck = $this->vehicle();
        $lights = $this->serializedItem();
        $units = $this->addUnits($lights, 2);
        $event = $this->event(10, 1);
        app(AllocationService::class)->reserveAssets($manager, $event, $lights, $units->pluck('id')->all());

        $this->actingAs($manager)->get("/app/logistics/trips/create?event={$event->id}&direction=outbound")->assertOk()->assertSee($units[0]->asset_tag);

        $local = fn ($c) => CarbonImmutable::parse($c)->setTimezone('Africa/Lagos')->format('Y-m-d\TH:i');
        $this->actingAs($manager)->post('/app/logistics/trips', [
            'event_id' => $event->id, 'direction' => 'outbound', 'vehicle_id' => $truck->id, 'driver_id' => $driver->id,
            'origin' => 'Main Warehouse', 'destination' => $event->venue,
            'departs_at' => $local($event->setup_starts_at->copy()->subHours(3)), 'arrives_at' => $local($event->setup_starts_at),
            'items' => $event->allocations()->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

        $trip = LogisticsTrip::firstOrFail();
        $this->assertMatchesRegularExpression('/^NEBO-TRP-\d{4}-00001$/', $trip->reference);
        $this->assertSame(2, $trip->items()->count());
        $this->assertSame($event->setup_starts_at->toDateTimeString(), $trip->arrives_at->toDateTimeString(), 'Lagos input is stored as UTC.');
        Notification::assertSentTo($driverUser, TripAssigned::class);

        $this->actingAs($manager)->get("/app/events/{$event->id}?tab=logistics")->assertOk()->assertSee($trip->reference);
        $this->actingAs($manager)->get("/app/logistics/trips/{$trip->reference}")->assertOk()->assertSee($units[0]->asset_tag);
        $this->actingAs($manager)->get('/app/logistics')->assertOk()->assertSee($trip->reference);
    }

    public function test_a_vehicle_cannot_be_on_two_overlapping_trips_or_out_of_service(): void
    {
        $admin = $this->superAdmin();
        $truck = $this->vehicle();
        $a = $this->event(10, 1);
        $b = $this->event(10, 1);
        $service = app(TripService::class);
        $service->plan($admin, $a, $this->tripData($a, ['vehicle_id' => $truck->id]));

        try {
            $service->plan($admin, $b, $this->tripData($b, ['vehicle_id' => $truck->id]));
            $this->fail('Vehicle double-booked.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('NEBO-TRP', $e->errors()['vehicle_id'][0]);
        }

        // Fine on another day; refused when the vehicle is out of service.
        $c = $this->event(20, 1);
        $service->plan($admin, $c, $this->tripData($c, ['vehicle_id' => $truck->id]));
        $broken = $this->vehicle(['status' => 'out_of_service']);
        $this->expectException(ValidationException::class);
        $service->plan($admin, $c, $this->tripData($c, ['vehicle_id' => $broken->id]));
    }

    public function test_driver_double_booking_needs_a_reason_and_is_audited(): void
    {
        $admin = $this->superAdmin();
        $driver = $this->staffMember('driver');
        $a = $this->event(10, 1);
        $b = $this->event(10, 1);
        $service = app(TripService::class);
        $service->plan($admin, $a, $this->tripData($a, ['driver_id' => $driver->id]));

        try {
            $service->plan($admin, $b, $this->tripData($b, ['crew' => [$driver->id]]));
            $this->fail('Driver double-booked without a reason.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('override_reason', $e->errors());
        }

        $trip = $service->plan($admin, $b, $this->tripData($b, ['crew' => [$driver->id], 'override_reason' => 'Short hop, back in time']));
        $this->assertDatabaseHas('audit_logs', ['event' => 'trip_double_booked', 'auditable_id' => (string) $trip->id]);
    }

    public function test_departure_and_arrival_move_units_through_transit_to_site(): void
    {
        [$event, $units, $admin] = $this->dispatchedEvent();
        $service = app(TripService::class);
        $trip = $service->plan($admin, $event, $this->tripData($event, ['vehicle_id' => $this->vehicle()->id, 'items' => $event->allocations()->pluck('id')->all()]));

        // A vehicle and driver are needed to leave.
        try {
            $service->transition($admin, $trip, TripStatus::InTransit);
            $this->fail('Left without a driver.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('driver', $e->errors()['status'][0]);
        }
        $trip->update(['driver_id' => $this->staffMember('driver')->id]);

        $service->transition($admin, $trip->fresh(), TripStatus::InTransit);
        $this->assertSame('in_transit', $units[0]->fresh()->status->code);
        $this->assertDatabaseHas('inventory_transactions', ['asset_id' => $units[0]->id, 'type' => 'in_transit', 'event_id' => $event->id]);
        $this->assertDatabaseHas('inventory_transactions', ['asset_id' => null, 'type' => 'in_transit', 'quantity' => 10]);

        // Going live while the kit is on the road doesn't mark it deployed yet.
        foreach ([EventStatus::Confirmed, EventStatus::InPreparation, EventStatus::InProgress] as $status) {
            app(EventWorkflow::class)->transition($admin, $event->fresh(), $status);
        }
        $this->assertSame('in_transit', $units[0]->fresh()->status->code);

        $service->transition($admin, $trip->fresh(), TripStatus::Arrived, null, 'Venue manager');
        $this->assertSame('deployed', $units[0]->fresh()->status->code);
        $this->assertSame('Venue manager', $trip->fresh()->received_by);
        $this->assertNotNull($trip->fresh()->arrived_at);
    }

    public function test_undispatched_items_block_departure(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        $unit = $this->addUnits($lights, 1)->first();
        $event = $this->event(10, 1);
        app(AllocationService::class)->reserveAssets($admin, $event, $lights, [$unit->id]);
        $trip = app(TripService::class)->plan($admin, $event, $this->tripData($event, [
            'vehicle_id' => $this->vehicle()->id, 'driver_id' => $this->staffMember('driver')->id, 'items' => $event->allocations()->pluck('id')->all(),
        ]));

        $this->expectException(ValidationException::class);
        app(TripService::class)->transition($admin, $trip, TripStatus::InTransit);
    }

    public function test_manifest_rules(): void
    {
        [$event, , $admin] = $this->dispatchedEvent();
        $other = $this->event(20, 1);
        $service = app(TripService::class);
        $ids = $event->allocations()->pluck('id')->all();
        $service->plan($admin, $event, $this->tripData($event, ['items' => $ids]));

        // The same items can't go out twice, and another event's items can't go at all.
        foreach ([[$event, $ids], [$other, $ids]] as [$e, $items]) {
            try {
                $service->plan($admin, $e, $this->tripData($e, ['items' => $items]));
                $this->fail('Manifest rule not enforced.');
            } catch (ValidationException $ex) {
                $this->assertArrayHasKey('items', $ex->errors());
            }
        }

        // But they can come back on a return trip.
        $return = $service->plan($admin, $event, $this->tripData($event, ['direction' => 'return', 'items' => $ids, 'origin' => $event->venue, 'destination' => 'Main Warehouse']));
        $this->assertSame(TripDirection::Return, $return->direction);
    }

    public function test_drivers_see_and_progress_only_their_trips(): void
    {
        [$event, $units, $admin] = $this->dispatchedEvent();
        $driverUser = $this->userWithRole('Crew');
        $driver = $this->staffMember('driver', $driverUser);
        $service = app(TripService::class);
        $mine = $service->plan($admin, $event, $this->tripData($event, ['vehicle_id' => $this->vehicle()->id, 'driver_id' => $driver->id, 'items' => $event->allocations()->pluck('id')->all()]));
        $other = $service->plan($admin, $this->event(20, 1), $this->tripData($this->event(21, 1), ['destination' => 'Someone else']));

        $this->actingAs($driverUser)->get('/app/logistics')->assertOk()->assertSee($mine->reference)->assertDontSee($other->reference);
        $this->actingAs($driverUser)->get("/app/logistics/trips/{$other->reference}")->assertForbidden();
        $this->actingAs($driverUser)->get('/app/logistics/vehicles')->assertForbidden();
        $this->actingAs($driverUser)->get('/app/logistics/trips/create')->assertForbidden();

        $this->actingAs($driverUser)->get('/app')->assertOk()->assertSee('Your trips')->assertSee($mine->reference);
        $this->actingAs($driverUser)->post("/app/logistics/trips/{$mine->reference}/status", ['status' => 'in_transit'])->assertSessionHasNoErrors();
        $this->actingAs($driverUser)->post("/app/logistics/trips/{$mine->reference}/status", ['status' => 'arrived', 'received_by' => 'Gate'])->assertSessionHasNoErrors();
        $this->assertSame(TripStatus::Arrived, $mine->fresh()->status);
        $this->assertSame('on_site', $units[0]->fresh()->status->code);

        // Drivers can't cancel.
        $this->actingAs($driverUser)->post("/app/logistics/trips/{$other->reference}/status", ['status' => 'cancelled', 'note' => 'x'])->assertForbidden();
    }

    public function test_cancelling_needs_a_reason(): void
    {
        $admin = $this->superAdmin();
        $event = $this->event(10, 1);
        $trip = app(TripService::class)->plan($admin, $event, $this->tripData($event));

        $this->actingAs($admin)->post("/app/logistics/trips/{$trip->reference}/status", ['status' => 'cancelled'])->assertSessionHasErrors('note');
        $this->actingAs($admin)->post("/app/logistics/trips/{$trip->reference}/status", ['status' => 'cancelled', 'note' => 'Client moved the date'])->assertSessionHasNoErrors();
        $this->assertSame(TripStatus::Cancelled, $trip->fresh()->status);
        $this->actingAs($admin)->get("/app/logistics/trips/{$trip->reference}/edit")->assertForbidden();
    }

    public function test_fleet_management(): void
    {
        $manager = $this->userWithRole('Operations Manager');
        $viewer = $this->userWithRole('Viewer');

        $this->actingAs($manager)->post('/app/logistics/vehicles', ['name' => 'Truck 1', 'registration' => ' lsd  482 xa ', 'type' => 'truck', 'status' => 'active'])->assertSessionHasNoErrors();
        $truck = Vehicle::firstOrFail();
        $this->assertSame('LSD 482 XA', $truck->registration);
        $this->actingAs($manager)->post('/app/logistics/vehicles', ['name' => 'Dup', 'registration' => 'LSD 482 XA', 'type' => 'truck', 'status' => 'active'])->assertSessionHasErrors('registration');

        // Booked vehicles can't be taken off the road or archived.
        $event = $this->event(10, 1);
        app(TripService::class)->plan($manager, $event, $this->tripData($event, ['vehicle_id' => $truck->id]));
        $this->actingAs($manager)->put("/app/logistics/vehicles/{$truck->id}", ['name' => 'Truck 1', 'registration' => 'LSD 482 XA', 'type' => 'truck', 'status' => 'retired'])->assertSessionHasErrors('status');
        $this->actingAs($manager)->delete("/app/logistics/vehicles/{$truck->id}")->assertSessionHasErrors('vehicle');

        $this->actingAs($viewer)->get('/app/logistics/vehicles')->assertOk()->assertSee('Truck 1');
        $this->actingAs($viewer)->get("/app/logistics/vehicles/{$truck->id}")->assertOk();
        $this->actingAs($viewer)->post('/app/logistics/vehicles', ['name' => 'x', 'registration' => 'X', 'type' => 'truck', 'status' => 'active'])->assertForbidden();
    }

    public function test_trips_show_on_the_calendar_and_in_search(): void
    {
        $admin = $this->superAdmin();
        $event = $this->event(3, 1);
        $trip = app(TripService::class)->plan($admin, $event, $this->tripData($event, ['destination' => 'Eko Convention Centre']));
        $day = CarbonImmutable::parse($trip->departs_at)->setTimezone('Africa/Lagos')->toDateString();

        $this->actingAs($admin)->get("/app/calendar?view=day&date={$day}")->assertOk()->assertSee('Main Warehouse → Eko Convention Centre');
        $this->actingAs($admin)->getJson('/app/search?q='.$trip->reference)->assertOk()->assertSee($trip->reference);
    }
}
