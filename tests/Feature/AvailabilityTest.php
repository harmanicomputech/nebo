<?php

namespace Tests\Feature;

use App\Enums\StockBucket;
use App\Models\AssetStatus;
use App\Services\Allocation\AllocationService;
use App\Services\Allocation\AvailabilityService;
use App\Services\Inventory\AssetService;
use App\Services\Inventory\StockService;
use App\Support\Settings;
use Tests\Concerns\InteractsWithEvents;
use Tests\Concerns\InteractsWithInventory;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use InteractsWithEvents, InteractsWithInventory;

    private function availability(): AvailabilityService
    {
        return app(AvailabilityService::class);
    }

    public function test_the_brief_example_overlapping_events_conflict(): void
    {
        // Event A: days 10–12, needs 12 moving lights. Event B: days 11–13.
        $admin = $this->superAdmin();
        $lights = $this->serializedItem(['name' => 'Moving Light']);
        $this->addUnits($lights, 20);
        $a = $this->event(10, 3);
        $b = $this->event(11, 3);
        $c = $this->event(20, 1);

        app(AllocationService::class)->autoReserve($admin, $a, $lights, 12);

        $forB = $this->availability()->forEvent($lights, $b);
        $this->assertSame(20, $forB->total);
        $this->assertSame(8, $forB->available);
        $this->assertSame(12, $forB->held);
        $this->assertSame($a->id, $forB->conflicts->first()['event']->id);
        $this->assertSame(12, $forB->conflicts->first()['quantity']);

        // A later event doesn't overlap, so all 20 are free.
        $this->assertSame(20, $this->availability()->forEvent($lights, $c)->available);
    }

    public function test_quantity_on_record_is_not_assumed_available(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        [$ok, $maint, $damaged, $lost] = $this->addUnits($lights, 4)->all();
        app(AssetService::class)->changeStatus($admin, $maint, AssetStatus::byCode('under_maintenance'));
        app(AssetService::class)->recordCondition($damaged, 'damaged');
        app(AssetService::class)->changeStatus($admin, $lost, AssetStatus::byCode('lost'));

        $result = $this->availability()->forEvent($lights, $this->event());

        $this->assertSame(1, $result->total);
        $this->assertSame([$ok->id], $result->assetIds);
    }

    public function test_bulk_stock_is_owned_stock_minus_overlapping_holds(): void
    {
        $admin = $this->superAdmin();
        $cable = $this->bulkItem();
        $this->receive($cable, 100);
        app(StockService::class)->moveBucket($cable, $this->location(), StockBucket::Available, StockBucket::Quarantine, 10);
        $a = $this->event(5, 2);
        $b = $this->event(6, 2);

        app(AllocationService::class)->reserveBulk($admin, $a, $cable, 60);

        $this->assertSame(30, $this->availability()->forEvent($cable, $b)->available);
        $this->assertSame(90, $this->availability()->forEvent($cable, $this->event(30))->available);
    }

    public function test_turnaround_buffer_widens_the_hold_window(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        $this->addUnits($lights, 1);
        $a = $this->event(10, 1, ['breakdown_ends_at' => now()->addDays(11)->setTime(3, 0)]);
        $b = $this->event(11, 1, ['setup_starts_at' => now()->addDays(11)->setTime(6, 0)]);

        app(AllocationService::class)->autoReserve($admin, $a, $lights, 1);
        $this->assertSame(1, $this->availability()->forEvent($lights, $b)->available); // 3am vs 6am: no overlap

        Settings::set('availability.buffer_hours', 4);
        $this->assertSame(0, $this->availability()->forEvent($lights, $b)->available);
    }

    public function test_availability_calendar_page(): void
    {
        $lights = $this->serializedItem(['name' => 'Calendar Light']);
        $this->addUnits($lights, 3);

        $this->actingAs($this->userWithRole('Inventory Manager'))->get('/app/availability')->assertOk()->assertSee('Calendar Light')->assertSee('3<span', false);
        $this->actingAs($this->userWithRole('Crew'))->get('/app/availability')->assertForbidden();
    }
}
