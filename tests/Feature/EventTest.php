<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Internal\Events\EventController;
use App\Models\Event;
use App\Models\EventRequest;
use App\Notifications\AssignedToEvent;
use App\Services\Booking\RequestWorkflow;
use App\Services\Events\EventWorkflow;
use App\Services\Events\TeamService;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithEvents;
use Tests\Concerns\SubmitsRequests;
use Tests\TestCase;

class EventTest extends TestCase
{
    use InteractsWithEvents, SubmitsRequests;

    public function test_production_manager_creates_an_event(): void
    {
        $pm = $this->userWithRole('Production Manager');

        $this->actingAs($pm)->post('/app/events', $this->eventPayload())->assertSessionHasNoErrors();

        $event = Event::firstOrFail();
        $this->assertMatchesRegularExpression('/^NEBO-EVT-\d{4}-00001$/', $event->reference);
        $this->assertSame(EventStatus::Planning, $event->status);
        // 18:00 Lagos is 17:00 UTC.
        $this->assertSame('17:00', $event->starts_at->utc()->format('H:i'));
        $this->assertNull($event->budget_kobo); // no financial.view
        $this->assertSame('planning', $event->statusChanges()->first()->to_status);
    }

    public function test_finance_aware_users_set_the_budget(): void
    {
        $this->actingAs($this->userWithRole('Operations Manager'))->post('/app/events', $this->eventPayload())->assertSessionHasNoErrors();

        $this->assertSame(250_000_000, Event::firstOrFail()->budget_kobo);
    }

    public function test_event_dates_must_make_sense(): void
    {
        $tz = config('nebo.display_timezone');
        $day = now($tz)->addDays(5);
        $pm = $this->userWithRole('Production Manager');

        $this->actingAs($pm)->post('/app/events', $this->eventPayload([
            'setup_starts_at' => $day->copy()->setTime(20, 0)->format('Y-m-d\TH:i'),
            'starts_at' => $day->copy()->setTime(18, 0)->format('Y-m-d\TH:i'),
        ]))->assertSessionHasErrors('starts_at');

        $this->actingAs($pm)->post('/app/events', $this->eventPayload([
            'starts_at' => $day->copy()->setTime(18, 0)->format('Y-m-d\TH:i'),
            'ends_at' => $day->copy()->setTime(17, 0)->format('Y-m-d\TH:i'),
        ]))->assertSessionHasErrors('ends_at');

        $this->assertSame(0, Event::count());
    }

    public function test_a_confirmed_request_converts_once_into_an_event(): void
    {
        $this->submitRequest();
        $this->flushSession();
        $request = EventRequest::firstOrFail();
        $pm = $this->userWithRole('Production Manager');
        $wf = app(RequestWorkflow::class);

        // Not yet won: refused.
        $this->actingAs($pm)->post("/app/requests/{$request->id}/event", $this->eventPayload(['customer_id' => null]))->assertSessionHasErrors('request');

        foreach ([RequestStatus::UnderReview, RequestStatus::QuotationPreparation, RequestStatus::QuotationSent, RequestStatus::Confirmed, RequestStatus::Approved] as $s) {
            $wf->transition($pm, $request->fresh(), $s);
        }
        $this->flushSession(); // drop the old input flashed by the refused attempt
        $this->actingAs($pm)->get("/app/requests/{$request->id}/event")->assertOk()->assertSee($request->event_name);
        $this->actingAs($pm)->post("/app/requests/{$request->id}/event", $this->eventPayload(['name' => $request->event_name, 'customer_id' => null, 'services' => $request->services->pluck('id')->all()]))->assertSessionHasNoErrors()->assertRedirect();

        $event = Event::firstOrFail();
        $request->refresh();
        $this->assertSame($request->customer_id, $event->customer_id);
        $this->assertSame($event->id, $request->converted_event_id);
        $this->assertSame(RequestStatus::ProductionScheduled, $request->status);
        $this->assertSame($request->services->pluck('id')->sort()->values()->all(), $event->services->pluck('id')->sort()->values()->all());

        $this->actingAs($pm)->post("/app/requests/{$request->id}/event", $this->eventPayload(['customer_id' => null]))->assertSessionHasErrors('request');
        $this->assertSame(1, Event::count());
    }

    public function test_status_workflow_with_history_and_rules(): void
    {
        $event = $this->event();
        $pm = $this->userWithRole('Production Manager');

        $this->actingAs($pm)->post("/app/events/{$event->id}/status", ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->actingAs($pm)->post("/app/events/{$event->id}/status", ['status' => 'on_hold'])->assertSessionHasErrors('note');
        $this->actingAs($pm)->post("/app/events/{$event->id}/status", ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame(EventStatus::Confirmed, $event->fresh()->status);
        $this->assertDatabaseHas('status_changes', ['statusable_id' => $event->id, 'from_status' => 'planning', 'to_status' => 'confirmed']);

        $this->actingAs($pm)->post("/app/events/{$event->id}/status", ['status' => 'cancelled', 'note' => 'Client postponed'])->assertSessionHasNoErrors();
        // Closed events are read-only.
        $this->actingAs($pm)->get("/app/events/{$event->id}/edit")->assertForbidden();
        $this->actingAs($pm)->post("/app/events/{$event->id}/status", ['status' => 'planning'])->assertForbidden();
    }

    public function test_staff_cannot_be_double_booked_without_an_override(): void
    {
        Notification::fake();
        $rigger = $this->staffMember('rigger', $user = $this->userWithRole('Crew'));
        $a = $this->event(10, 2);
        $b = $this->event(11, 1); // overlaps a
        $c = $this->event(20, 1); // doesn't
        $pm = $this->userWithRole('Production Manager');

        $this->actingAs($pm)->post("/app/events/{$a->id}/team", ['staff_id' => $rigger->id, 'role' => 'rigger'])->assertSessionHasNoErrors();
        Notification::assertSentTo($user, AssignedToEvent::class);

        $this->actingAs($pm)->post("/app/events/{$b->id}/team", ['staff_id' => $rigger->id, 'role' => 'rigger'])->assertSessionHasErrors('staff_id');
        $this->actingAs($pm)->post("/app/events/{$c->id}/team", ['staff_id' => $rigger->id, 'role' => 'rigger'])->assertSessionHasNoErrors();
        $this->actingAs($pm)->post("/app/events/{$b->id}/team", ['staff_id' => $rigger->id, 'role' => 'rigger', 'override_reason' => 'Short changeover; same venue'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['event' => 'team_assigned', 'auditable_id' => (string) $b->id]);
        $this->actingAs($pm)->post("/app/events/{$a->id}/team", ['staff_id' => $rigger->id, 'role' => 'rigger'])->assertSessionHasErrors('staff_id'); // already on it

        // A cancelled event no longer blocks.
        $other = $this->staffMember();
        app(TeamService::class)->assign($a, $other, 'rigger');
        app(EventWorkflow::class)->transition($pm, $a, EventStatus::Cancelled, 'Called off');
        $this->assertCount(0, app(TeamService::class)->conflicts($other, $b));
    }

    public function test_crew_see_only_events_they_are_on(): void
    {
        $crewUser = $this->userWithRole('Crew');
        $mine = $this->event(5);
        $theirs = $this->event(6, 1, ['name' => 'Not My Gig']);
        app(TeamService::class)->assign($mine, $this->staffMember('general_crew', $crewUser), 'general_crew');

        $this->actingAs($crewUser)->get('/app/events?when=all')->assertOk()->assertSee($mine->name)->assertDontSee('Not My Gig');
        $this->actingAs($crewUser)->get("/app/events/{$mine->id}")->assertOk();
        $this->actingAs($crewUser)->get("/app/events/{$theirs->id}")->assertForbidden();
        $this->actingAs($crewUser)->get('/app/events/create')->assertForbidden();
        $this->actingAs($crewUser)->get("/app/calendar?date={$mine->starts_at->toDateString()}")->assertOk()->assertSee($mine->name)->assertDontSee('Not My Gig');
        $this->actingAs($crewUser)->getJson('/app/search?q=Gig')->assertJsonPath('groups', []);
    }

    public function test_calendar_shows_setup_show_and_breakdown_days(): void
    {
        $event = $this->event(3, 2, ['name' => 'Two Day Festival', 'setup_starts_at' => now()->addDays(2)->setTime(8, 0)]);
        $admin = $this->superAdmin();

        $html = $this->actingAs($admin)->get('/app/calendar?view=week&date='.now()->addDays(3)->toDateString())->assertOk()->getContent();
        $this->assertStringContainsString('Two Day Festival', $html);
        $this->assertStringContainsString('Setup', $html);
        $this->actingAs($admin)->get('/app/calendar?view=day&date='.now()->addDays(4)->toDateString())->assertSee('Two Day Festival')->assertSee('Show');
        $this->actingAs($admin)->get('/app/calendar?view=day&date='.now()->addDays(30)->toDateString())->assertSee('Nothing scheduled');
    }

    public function test_notes_documents_and_archiving(): void
    {
        $event = $this->event();
        $ops = $this->userWithRole('Operations Manager');

        $this->actingAs($ops)->post("/app/events/{$event->id}/notes", ['body' => 'Venue power is 3-phase'])->assertSessionHasNoErrors();
        $this->actingAs($ops)->get("/app/events/{$event->id}?tab=notes")->assertSee('Venue power is 3-phase');
        $this->actingAs($this->userWithRole('Viewer'))->post("/app/events/{$event->id}/notes", ['body' => 'x'])->assertForbidden();

        $this->actingAs($ops)->delete("/app/events/{$event->id}")->assertRedirect();
        $this->assertSoftDeleted($event);
        $this->actingAs($ops)->get("/app/events/{$event->id}")->assertOk();
        $this->actingAs($ops)->post("/app/events/{$event->id}/restore")->assertRedirect();
        $this->assertNotSoftDeleted($event);
    }

    public function test_every_workspace_tab_opens(): void
    {
        $event = $this->event();
        $admin = $this->superAdmin();

        foreach (array_keys(EventController::TABS) as $tab) {
            $this->actingAs($admin)->get("/app/events/{$event->id}?tab={$tab}")->assertOk()->assertDontSee('· Planned');
        }
    }

    public function test_staff_profiles(): void
    {
        $ops = $this->userWithRole('Operations Manager');
        $account = $this->userWithRole('Crew');

        $this->actingAs($ops)->post('/app/staff', ['name' => 'Ada Rigger', 'role' => 'rigger', 'phone' => '0803 111 2222', 'user_id' => $account->id, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('staff', ['name' => 'Ada Rigger', 'phone' => '+2348031112222', 'user_id' => $account->id]);

        $this->actingAs($ops)->post('/app/staff', ['name' => 'Dup', 'role' => 'rigger', 'user_id' => $account->id, 'is_active' => 1])->assertSessionHasErrors('user_id');
        $this->actingAs($this->userWithRole('Viewer'))->get('/app/staff')->assertOk(); // read-only
        $this->actingAs($this->userWithRole('Crew'))->get('/app/staff')->assertForbidden();
        $this->actingAs($this->userWithRole('Viewer'))->post('/app/staff', ['name' => 'X', 'role' => 'rigger', 'is_active' => 1])->assertForbidden();
    }

    public function test_dashboard_lists_todays_events(): void
    {
        $this->event(0, 1, ['name' => 'Happening Today', 'setup_starts_at' => now()->subHours(2), 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(5), 'breakdown_ends_at' => now()->addHours(8)]);

        $this->actingAs($this->userWithRole('Operations Manager'))->get('/app')->assertSee('Happening Today');
    }
}
