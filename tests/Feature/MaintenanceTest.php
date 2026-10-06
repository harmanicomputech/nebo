<?php

namespace Tests\Feature;

use App\Enums\LoadStatus;
use App\Enums\MaintenanceStatus;
use App\Models\ConditionReport;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Notifications\MaintenanceAssigned;
use App\Notifications\MaintenanceDue;
use App\Services\Allocation\AllocationService;
use App\Services\Allocation\AvailabilityService;
use App\Services\Allocation\LoadListService;
use App\Services\Allocation\ReturnService;
use App\Services\Events\EventService;
use App\Services\Maintenance\MaintenanceReminders;
use App\Services\Maintenance\MaintenanceScheduler;
use App\Services\Maintenance\MaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithEvents;
use Tests\Concerns\InteractsWithInventory;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use InteractsWithEvents, InteractsWithInventory;

    public function test_reporting_a_fault_takes_the_unit_out_of_service(): void
    {
        $manager = $this->userWithRole('Inventory Manager');
        $unit = $this->addUnits($this->serializedItem(), 1)->first();

        $this->actingAs($manager)->post('/app/maintenance', [
            'asset_tag' => $unit->asset_tag, 'type' => 'repair', 'priority' => 'high',
            'issue' => 'Pan motor grinding', 'out_of_service' => '1',
        ])->assertSessionHasNoErrors();

        $record = MaintenanceRecord::firstOrFail();
        $this->assertMatchesRegularExpression('/^NEBO-MNT-\d{4}-00001$/', $record->reference);
        $this->assertSame(MaintenanceStatus::Reported, $record->status);
        $this->assertSame('maintenance_required', $unit->fresh()->status->code);
        $this->assertFalse($unit->fresh()->isAllocatable());
        $this->assertDatabaseHas('inventory_transactions', ['asset_id' => $unit->id, 'type' => 'status_changed']);
        $this->assertDatabaseHas('status_changes', ['statusable_id' => $record->id, 'to_status' => 'reported']);

        $this->actingAs($manager)->get("/app/maintenance/{$record->reference}")->assertOk()->assertSee('Pan motor grinding');
        $this->actingAs($manager)->get('/app/maintenance')->assertOk()->assertSee($unit->asset_tag);
        $this->actingAs($manager)->get("/app/inventory/assets/{$unit->id}")->assertOk()->assertSee($record->reference);
    }

    public function test_planned_service_leaves_the_unit_in_service_but_blocks_its_window(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        [$unit, $other] = $this->addUnits($lights, 2)->all();
        $event = $this->event(10, 1);
        [$from, $to] = app(AvailabilityService::class)->window($event);

        $record = app(MaintenanceService::class)->report($admin, $unit, [
            'type' => 'preventive', 'issue' => 'Quarterly service',
            'scheduled_starts_at' => CarbonImmutable::parse($from)->addHours(2), 'scheduled_ends_at' => CarbonImmutable::parse($from)->addHours(6),
        ]);

        $this->assertSame(MaintenanceStatus::Scheduled, $record->status);
        $this->assertSame('available', $unit->fresh()->status->code);

        $availability = app(AvailabilityService::class)->forEquipment($lights, $from, $to);
        $this->assertSame(1, $availability->available);
        $this->assertSame(1, $availability->blocked);
        $this->assertSame([$other->id], $availability->assetIds);

        // The day-by-day grid sees the window too (batched blocker windows).
        $day = CarbonImmutable::parse($from)->addHours(2)->setTimezone('Africa/Lagos')->startOfDay();
        $grid = app(AvailabilityService::class)->timeline(collect([$lights]), $day, 1);
        $this->assertSame(1, $grid[$lights->id][0]['available']);

        $this->expectException(ValidationException::class);
        app(AllocationService::class)->reserveAssets($admin, $event, $lights, [$unit->id]);
    }

    public function test_maintenance_cannot_be_scheduled_over_a_booking_and_events_cannot_move_onto_maintenance(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        $unit = $this->addUnits($lights, 1)->first();
        $event = $this->event(10, 1);
        app(AllocationService::class)->reserveAssets($admin, $event, $lights, [$unit->id]);

        try {
            app(MaintenanceService::class)->report($admin, $unit->fresh(), ['type' => 'preventive', 'issue' => 'Service',
                'scheduled_starts_at' => $event->starts_at, 'scheduled_ends_at' => $event->ends_at]);
            $this->fail('Maintenance was scheduled over a booking.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($event->name, $e->errors()['scheduled_starts_at'][0]);
        }

        // Maintenance in day 20; moving the event onto day 20 is refused.
        app(MaintenanceService::class)->report($admin, $unit->fresh(), ['type' => 'preventive', 'issue' => 'Service',
            'scheduled_starts_at' => now()->addDays(20)->setTime(6, 0), 'scheduled_ends_at' => now()->addDays(21)->setTime(6, 0)]);

        $this->expectException(ValidationException::class);
        app(EventService::class)->update($event->fresh(), [
            'setup_starts_at' => now()->addDays(20)->setTime(8, 0), 'starts_at' => now()->addDays(20)->setTime(12, 0),
            'ends_at' => now()->addDays(20)->setTime(22, 0), 'breakdown_ends_at' => now()->addDays(21)->setTime(3, 0),
        ]);
    }

    public function test_work_cannot_start_on_a_booked_unit_until_it_is_released(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        $unit = $this->addUnits($lights, 1)->first();
        $event = $this->event(10, 1);
        app(AllocationService::class)->reserveAssets($admin, $event, $lights, [$unit->id]);
        $record = app(MaintenanceService::class)->report($admin, $unit->fresh(), ['type' => 'repair', 'issue' => 'Flicker', 'out_of_service' => true]);

        $this->assertSame('allocated', $unit->fresh()->status->code, 'A fault on a booked unit leaves the booking alone.');

        try {
            app(MaintenanceService::class)->start($admin, $record);
            $this->fail('Work started on a booked unit.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($event->name, $e->errors()['status'][0]);
        }

        app(AllocationService::class)->release($admin, $event->allocations()->first(), 'Faulty');
        app(MaintenanceService::class)->start($admin, $record->fresh());
        $this->assertSame('under_maintenance', $unit->fresh()->status->code);
    }

    public function test_completing_a_job_records_condition_and_returns_the_unit_to_service(): void
    {
        $admin = $this->superAdmin();
        $unit = $this->addUnits($this->serializedItem(), 1)->first();
        $schedule = app(MaintenanceScheduler::class)->create($admin, $unit, ['type' => 'preventive', 'interval_days' => 90, 'next_due_on' => now()->subDays(3)->toDateString()]);
        $record = app(MaintenanceScheduler::class)->openJob($admin, $schedule);
        app(MaintenanceService::class)->start($admin, $record);

        $this->actingAs($admin)->post("/app/maintenance/{$record->reference}/complete", [
            'work_done' => 'Cleaned optics, replaced fan', 'outcome_condition' => 'good', 'cost' => '45000', 'parts_used' => '1 fan',
        ])->assertSessionHasNoErrors();

        $record->refresh();
        $unit->refresh();
        $today = CarbonImmutable::now(config('nebo.display_timezone'))->startOfDay();
        $this->assertSame(MaintenanceStatus::Completed, $record->status);
        $this->assertSame(4_500_000, $record->cost_kobo);
        $this->assertSame('available', $unit->status->code);
        $this->assertSame('good', $unit->condition);
        $this->assertDatabaseHas('inventory_transactions', ['asset_id' => $unit->id, 'type' => 'repaired']);
        $this->assertDatabaseHas('condition_reports', ['asset_id' => $unit->id, 'source' => 'maintenance', 'maintenance_record_id' => $record->id]);
        $this->assertSame($today->addDays(90)->toDateString(), $schedule->fresh()->next_due_on->toDateString());
        $this->assertSame($today->toDateString(), $schedule->fresh()->last_done_on->toDateString());
        $this->assertSame($today->addDays(90)->toDateString(), $unit->next_maintenance_due_on->toDateString());
    }

    public function test_a_job_finished_with_a_blocking_condition_keeps_the_unit_out(): void
    {
        $admin = $this->superAdmin();
        $unit = $this->addUnits($this->serializedItem(), 1)->first();
        $record = app(MaintenanceService::class)->report($admin, $unit, ['type' => 'repair', 'issue' => 'Dead', 'out_of_service' => true]);
        app(MaintenanceService::class)->start($admin, $record);
        app(MaintenanceService::class)->complete($admin, $record->fresh(), ['work_done' => 'Board is fried', 'outcome_condition' => 'damaged']);

        $this->assertSame('damaged', $unit->fresh()->status->code);
        $this->assertFalse($unit->fresh()->isAllocatable());
    }

    public function test_cancelling_a_job_returns_the_unit_to_service(): void
    {
        $admin = $this->superAdmin();
        $unit = $this->addUnits($this->serializedItem(), 1)->first();
        $record = app(MaintenanceService::class)->report($admin, $unit, ['type' => 'repair', 'issue' => 'Odd noise', 'out_of_service' => true]);

        $this->actingAs($admin)->post("/app/maintenance/{$record->reference}/cancel", ['note' => ''])->assertSessionHasErrors('note');
        $this->actingAs($admin)->post("/app/maintenance/{$record->reference}/cancel", ['note' => 'Could not reproduce'])->assertSessionHasNoErrors();

        $this->assertSame(MaintenanceStatus::Cancelled, $record->fresh()->status);
        $this->assertSame('available', $unit->fresh()->status->code);

        // Closed jobs can't be changed.
        $this->actingAs($admin)->post("/app/maintenance/{$record->reference}/start")->assertForbidden();
    }

    public function test_inspection_with_photos_and_a_follow_up_job(): void
    {
        Storage::fake('local');
        $tech = $this->userWithRole('Technician');
        $unit = $this->addUnits($this->serializedItem(), 1)->first();

        $this->actingAs($tech)->post("/app/inventory/assets/{$unit->id}/condition", [
            'condition' => 'damaged', 'note' => 'Lens cracked',
            'photos' => [UploadedFile::fake()->createWithContent('script.jpg', '<?php echo 1;')],
        ])->assertSessionHasErrors('photos.0');

        $this->actingAs($tech)->post("/app/inventory/assets/{$unit->id}/condition", [
            'condition' => 'damaged', 'note' => 'Lens cracked',
            'photos' => [UploadedFile::fake()->image('lens.jpg', 400, 300)],
            'raise_job' => '1', 'job_type' => 'repair', 'job_priority' => 'high', 'job_issue' => 'Replace front lens',
        ])->assertSessionHasNoErrors();

        $report = ConditionReport::firstOrFail();
        $this->assertSame(['good', 'damaged', 'inspection'], [$report->from_condition, $report->to_condition, $report->source]);
        $this->assertCount(1, $report->documents);
        $this->assertSame('damaged', $unit->fresh()->status->code);
        $job = MaintenanceRecord::firstOrFail();
        $this->assertSame(['inspection', 'Replace front lens'], [$job->source, $job->issue]);

        // The photo is served to people who can see the asset.
        $this->actingAs($tech)->get(route('app.documents.download', $report->documents->first()))->assertOk();
        $this->actingAs($tech)->get("/app/inventory/assets/{$unit->id}")->assertOk()->assertSee('Condition history')->assertSee('Lens cracked');
    }

    public function test_damaged_returns_open_a_job_and_condition_entry(): void
    {
        $admin = $this->superAdmin();
        $lights = $this->serializedItem();
        [$ok, $broken, $check] = $this->addUnits($lights, 3)->all();
        $event = $this->event(0, 1, ['setup_starts_at' => now()->subHour(), 'starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(6), 'breakdown_ends_at' => now()->addHours(9)]);
        app(AllocationService::class)->reserveAssets($admin, $event, $lights, [$ok->id, $broken->id, $check->id]);
        $list = app(LoadListService::class)->sync($admin, $event);
        app(LoadListService::class)->advanceAll($admin, $list, LoadStatus::Checked);
        app(LoadListService::class)->dispatch($admin, $list->fresh());
        $byAsset = $event->allocations()->get()->keyBy('asset_id');

        app(ReturnService::class)->process($admin, $event->fresh(), [
            $byAsset[$ok->id]->id => ['include' => 1, 'outcome' => 'returned'],
            $byAsset[$broken->id]->id => ['include' => 1, 'outcome' => 'damaged', 'note' => 'Yoke bent'],
            $byAsset[$check->id]->id => ['include' => 1, 'outcome' => 'needs_inspection'],
        ], $this->location()->id);

        $this->assertSame(0, $ok->maintenanceRecords()->count());
        $job = $broken->maintenanceRecords()->firstOrFail();
        $this->assertSame(['return', 'repair', 'high', $event->id], [$job->source, $job->type, $job->priority->value, $job->event_id]);
        $this->assertSame('inspection', $check->maintenanceRecords()->firstOrFail()->type);
        $this->assertDatabaseHas('condition_reports', ['asset_id' => $broken->id, 'source' => 'return', 'to_condition' => 'damaged', 'event_id' => $event->id]);
    }

    public function test_schedules_for_every_unit_and_reminders(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();
        $manager = $this->userWithRole('Inventory Manager');
        $lights = $this->serializedItem();
        $units = $this->addUnits($lights, 3);

        $this->actingAs($manager)->post('/app/maintenance/schedules', [
            'scope' => 'equipment', 'equipment_id' => $lights->id, 'type' => 'preventive', 'interval_days' => 90, 'next_due_on' => now()->addDays(3)->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, MaintenanceSchedule::count());
        $this->assertSame(now()->addDays(3)->toDateString(), $units[0]->fresh()->next_maintenance_due_on->toDateString());

        // A second schedule of the same type for a unit is refused.
        $this->actingAs($manager)->post('/app/maintenance/schedules', [
            'scope' => 'asset', 'asset_id' => $units[0]->id, 'type' => 'preventive', 'interval_days' => 30, 'next_due_on' => now()->toDateString(),
        ])->assertSessionHasErrors('type');

        // Reminded once inside the lead time, not again the next day; open jobs are skipped.
        app(MaintenanceScheduler::class)->openJob($admin, MaintenanceSchedule::where('asset_id', $units[2]->id)->first());
        $this->assertSame(['due_soon' => 2, 'overdue' => 0], app(MaintenanceReminders::class)->send());
        Notification::assertSentTo($manager, MaintenanceDue::class);
        $this->assertSame(['due_soon' => 0, 'overdue' => 0], app(MaintenanceReminders::class)->send());

        // Overdue schedules are reminded again a week later.
        $this->travel(11)->days();
        $this->assertSame(['due_soon' => 0, 'overdue' => 2], app(MaintenanceReminders::class)->send());

        $this->actingAs($manager)->get('/app/maintenance?view=due')->assertOk()->assertSee($units[0]->asset_tag)->assertSee('Overdue');
        $this->actingAs($manager)->get('/app/maintenance?view=schedules')->assertOk();
    }

    public function test_technicians_see_only_their_jobs_and_viewers_cannot_log_jobs(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();
        $tech = $this->userWithRole('Technician');
        $staff = $this->staffMember('lighting_technician', $tech);
        [$a, $b] = $this->addUnits($this->serializedItem(), 2)->all();
        $mine = app(MaintenanceService::class)->report($admin, $a, ['type' => 'repair', 'issue' => 'Mine to fix', 'technician_id' => $staff->id]);
        $other = app(MaintenanceService::class)->report($admin, $b, ['type' => 'repair', 'issue' => 'Someone else']);

        Notification::assertSentTo($tech, MaintenanceAssigned::class);
        $this->actingAs($tech)->get('/app/maintenance')->assertOk()->assertSee('Mine to fix')->assertDontSee('Someone else');
        $this->actingAs($tech)->get("/app/maintenance/{$mine->reference}")->assertOk();
        $this->actingAs($tech)->get("/app/maintenance/{$other->reference}")->assertForbidden();
        $this->actingAs($tech)->post("/app/maintenance/{$mine->reference}/start")->assertSessionHasNoErrors();
        $this->assertSame('under_maintenance', $a->fresh()->status->code);
        $this->actingAs($tech)->get('/app/maintenance?view=due')->assertOk()->assertDontSee('Schedules');

        $viewer = $this->userWithRole('Viewer');
        $this->actingAs($viewer)->get('/app/maintenance')->assertOk();
        $this->actingAs($viewer)->get('/app/maintenance/create')->assertForbidden();
        $this->actingAs($viewer)->post('/app/maintenance', ['asset_tag' => $b->asset_tag, 'type' => 'repair', 'priority' => 'low', 'issue' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->post("/app/inventory/assets/{$b->id}/condition", ['condition' => 'good'])->assertForbidden();

        $crew = $this->userWithRole('Crew');
        $this->actingAs($crew)->get('/app/maintenance')->assertForbidden();
    }

    public function test_scheduled_maintenance_shows_on_the_calendar(): void
    {
        $admin = $this->superAdmin();
        $unit = $this->addUnits($this->serializedItem(), 1)->first();
        $starts = CarbonImmutable::now(config('nebo.display_timezone'))->addDays(2)->setTime(9, 0);
        app(MaintenanceService::class)->report($admin, $unit, ['type' => 'calibration', 'issue' => 'Calibrate',
            'scheduled_starts_at' => $starts->utc(), 'scheduled_ends_at' => $starts->addHours(4)->utc()]);

        $this->actingAs($admin)->get('/app/calendar?view=day&date='.$starts->toDateString())->assertOk()->assertSee($unit->asset_tag.' · Calibration')->assertSee('Maintenance');
        $this->actingAs($this->userWithRole('Crew'))->get('/app/calendar?view=day&date='.$starts->toDateString())->assertOk()->assertDontSee($unit->asset_tag);
    }
}
