<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\EventRequest;
use App\Models\Service;
use App\Notifications\RequestAssigned;
use App\Notifications\RequestStatusChanged;
use App\Services\Booking\RequestWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SubmitsRequests;
use Tests\TestCase;

class RequestWorkflowTest extends TestCase
{
    use SubmitsRequests;

    private function makeRequest(array $overrides = []): EventRequest
    {
        $this->submitRequest($overrides);
        $this->flushSession();

        return EventRequest::latest('id')->firstOrFail();
    }

    public function test_staff_with_access_see_requests_and_others_dont(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->userWithRole('Viewer'))->get('/app/requests')->assertOk()->assertSee($request->event_name);
        $this->actingAs($this->userWithRole('Viewer'))->get("/app/requests/{$request->id}")->assertOk()->assertSee($request->requirements);
        $this->actingAs($this->userWithRole('Crew'))->get('/app/requests')->assertForbidden();
        $this->actingAs($this->userWithRole('Crew'))->get("/app/requests/{$request->id}")->assertForbidden();
    }

    public function test_status_moves_through_the_workflow_with_history_and_audit(): void
    {
        Notification::fake();
        $request = $this->makeRequest();
        $pm = $this->userWithRole('Production Manager');
        $assignee = $this->userWithRole('Operations Manager');
        app(RequestWorkflow::class)->assign($pm, $request, $assignee);
        Notification::assertSentTo($assignee, RequestAssigned::class);

        $this->actingAs($pm)->post("/app/requests/{$request->id}/status", ['status' => 'under_review', 'note' => 'Looks good'])->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::UnderReview, $request->fresh()->status);
        $this->assertDatabaseHas('status_changes', ['statusable_id' => $request->id, 'from_status' => 'new', 'to_status' => 'under_review', 'user_id' => $pm->id, 'note' => 'Looks good']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'status_changed', 'auditable_type' => 'EventRequest', 'auditable_id' => (string) $request->id]);
        Notification::assertSentTo($assignee, RequestStatusChanged::class);
    }

    public function test_invalid_transitions_and_unexplained_cancellations_are_refused(): void
    {
        $request = $this->makeRequest();
        $pm = $this->userWithRole('Production Manager');

        $this->actingAs($pm)->post("/app/requests/{$request->id}/status", ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->actingAs($pm)->post("/app/requests/{$request->id}/status", ['status' => 'cancelled'])->assertSessionHasErrors('note');
        $this->actingAs($pm)->post("/app/requests/{$request->id}/status", ['status' => 'declined', 'note' => 'Date unavailable'])->assertSessionHasNoErrors();

        // Closed requests are final.
        $this->actingAs($pm)->post("/app/requests/{$request->id}/status", ['status' => 'under_review'])->assertForbidden();
        $this->assertSame(RequestStatus::Declined, $request->fresh()->status);
    }

    public function test_read_only_users_cannot_act_on_requests(): void
    {
        $request = $this->makeRequest();
        $viewer = $this->userWithRole('Viewer');

        $this->actingAs($viewer)->post("/app/requests/{$request->id}/status", ['status' => 'under_review'])->assertForbidden();
        $this->actingAs($viewer)->post("/app/requests/{$request->id}/assign", ['assigned_to' => $viewer->id])->assertForbidden();
        $this->actingAs($viewer)->post("/app/requests/{$request->id}/notes", ['body' => 'hi'])->assertForbidden();
    }

    public function test_requests_can_only_be_assigned_to_people_who_can_see_them(): void
    {
        $request = $this->makeRequest();

        $this->actingAs($this->userWithRole('Operations Manager'))->post("/app/requests/{$request->id}/assign", ['assigned_to' => $this->userWithRole('Crew')->id])
            ->assertSessionHasErrors('assigned_to');
    }

    public function test_notes_and_documents(): void
    {
        Storage::fake('local');
        $request = $this->makeRequest();
        $ops = $this->userWithRole('Operations Manager');

        $this->actingAs($ops)->post("/app/requests/{$request->id}/notes", ['body' => 'Called the client'])->assertSessionHasNoErrors();
        $this->assertSame('Called the client', $request->notes()->first()->body);

        $this->actingAs($ops)->post("/app/documents/request/{$request->id}", ['files' => [UploadedFile::fake()->createWithContent('site-survey.pdf', "%PDF-1.4\n%%EOF")], 'category' => 'stage_layout'])
            ->assertSessionHasNoErrors();
        $doc = $request->documents()->firstOrFail();
        $this->assertSame('stage_layout', $doc->category);

        $this->actingAs($this->userWithRole('Viewer'))->get("/app/documents/{$doc->id}")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->userWithRole('Crew'))->get("/app/documents/{$doc->id}")->assertForbidden();
        $this->actingAs($this->userWithRole('Viewer'))->delete("/app/documents/{$doc->id}")->assertForbidden();
        $this->actingAs($ops)->delete("/app/documents/{$doc->id}")->assertRedirect();
        $this->assertSoftDeleted($doc);
        $this->actingAs($ops)->post('/app/documents/nonsense/1', ['files' => []])->assertNotFound();
    }

    public function test_services_catalogue_drives_the_public_form(): void
    {
        $finance = $this->userWithRole('Finance / Commercial');

        $this->actingAs($finance)->post('/app/settings/services', ['name' => 'Drone Coverage', 'is_public' => 1, 'is_active' => 1])->assertSessionHasNoErrors();
        $drone = Service::where('name', 'Drone Coverage')->firstOrFail();
        $this->post('/logout');
        $this->get('/request')->assertSee('Drone Coverage');

        $this->actingAs($finance)->put("/app/settings/services/{$drone->id}", ['name' => 'Drone Coverage', 'is_public' => 1, 'is_active' => 0])->assertSessionHasNoErrors();
        $this->post('/logout');
        $this->get('/request')->assertDontSee('Drone Coverage');

        $this->actingAs($this->userWithRole('Viewer'))->get('/app/settings/services')->assertForbidden();
    }

    public function test_dashboard_and_search_include_requests(): void
    {
        $request = $this->makeRequest(['event_name' => 'Findable Gala']);

        $this->actingAs($this->userWithRole('Operations Manager'))->get('/app')->assertSee('Findable Gala')->assertSee('Production requests');
        $this->actingAs($this->userWithRole('Viewer'))->getJson('/app/search?q=Findable')->assertJsonPath('groups.0.label', 'Requests');
        $this->actingAs($this->userWithRole('Crew'))->get('/app')->assertDontSee('Findable Gala');
    }
}
