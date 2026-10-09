<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Mail\RequestReceived;
use App\Models\Customer;
use App\Models\EventRequest;
use App\Models\Service;
use App\Notifications\NewEventRequest;
use App\Services\Booking\RequestWorkflow;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithInventory;
use Tests\Concerns\SubmitsRequests;
use Tests\TestCase;

class PublicRequestTest extends TestCase
{
    use InteractsWithInventory, SubmitsRequests;

    public function test_form_shows_database_services_and_no_internal_data(): void
    {
        $this->serializedItem(['name' => 'Secret Internal Fixture']);

        $this->get('/request')->assertOk()
            ->assertSee('LET’S BUILD YOUR STAGE')->assertSee('SUBMIT YOUR REQUEST')
            ->assertSee('LED Screens &amp; Displays', false)->assertSee('Award Ceremony')->assertSee('Prefer to discuss')
            ->assertDontSee('Secret Internal Fixture');
    }

    public function test_the_website_can_link_to_the_form_with_services_ticked(): void
    {
        $stage = Service::where('slug', 'stage-rigging')->firstOrFail();
        $screens = Service::where('slug', 'led-screens-displays')->firstOrFail();

        $this->get('/request?service=stage-rigging,led-screens-displays,not-a-service')->assertOk()
            ->assertViewHas('preselected', [(string) $stage->id, (string) $screens->id]);
        $this->get('/request')->assertOk()->assertViewHas('preselected', []);
    }

    public function test_a_valid_request_is_saved_with_a_reference_and_history(): void
    {
        Notification::fake();

        $response = $this->submitRequest();
        $request = EventRequest::firstOrFail();

        $response->assertRedirect(route('requests.received', $request->public_token));
        $this->assertMatchesRegularExpression('/^NEBO-REQ-\d{4}-00001$/', $request->reference);
        $this->assertSame(RequestStatus::New, $request->status);
        $this->assertSame('jane@example.com', $request->email);
        $this->assertSame('+2348031234567', $request->phone);
        $this->assertCount(2, $request->services);
        $this->assertSame('new', $request->statusChanges()->first()->to_status);
        $this->assertSame('Jane Okafor', $request->customer->name);

        $this->get(route('requests.received', $request->public_token))->assertOk()->assertSee($request->reference);
    }

    public function test_setup_time_entered_in_lagos_time_is_stored_in_utc(): void
    {
        $date = now('Africa/Lagos')->addDays(10);
        $this->submitRequest(['event_date' => $date->toDateString(), 'setup_at' => $date->copy()->setTime(10, 0)->format('Y-m-d\TH:i')]);

        $this->assertSame('09:00', EventRequest::firstOrFail()->setup_at->utc()->format('H:i'));
    }

    public function test_required_fields_and_business_rules_are_validated_server_side(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class); // many posts; throttling has its own test
        $this->submitRequest([
            'event_name' => '', 'event_date' => now()->subDay()->toDateString(), 'phone' => '123', 'email' => 'nope',
            'services' => [], 'requirements' => '', 'budget_range' => 'free', 'contact_person' => '',
        ])->assertSessionHasErrors(['event_name', 'event_date', 'phone', 'email', 'services', 'requirements', 'budget_range', 'contact_person']);

        $this->submitRequest(['event_type' => 'other', 'event_type_other' => ''])->assertSessionHasErrors('event_type_other');
        $this->submitRequest(['services' => ['other'], 'services_other' => ''])->assertSessionHasErrors('services_other');
        $this->submitRequest(['setup_at' => now('Africa/Lagos')->addDays(45)->format('Y-m-d\TH:i')])->assertSessionHasErrors('setup_at');
        $this->submitRequest(['starts_at' => '2030-01-02T10:00', 'ends_at' => '2030-01-01T10:00'])->assertSessionHasErrors('ends_at');
        $this->submitRequest(['services' => ['99999']])->assertSessionHasErrors('services.0');

        $this->assertSame(0, EventRequest::count());
    }

    public function test_a_resubmitted_form_does_not_create_a_duplicate(): void
    {
        $payload = $this->requestPayload();

        $first = $this->post('/request', $payload);
        $second = $this->post('/request', $payload);

        $this->assertSame(1, EventRequest::count());
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
    }

    public function test_bots_are_stopped_by_the_honeypot_and_fill_time(): void
    {
        $this->submitRequest(['website' => 'http://spam.example'])->assertSessionHasErrors('website');
        $this->submitRequest(['form_started' => Crypt::encryptString((string) now()->timestamp)])->assertSessionHasErrors('form');
        $this->submitRequest(['form_started' => 'tampered'])->assertSessionHasErrors('form');

        $this->assertSame(0, EventRequest::count());
    }

    public function test_submissions_are_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->submitRequest(['event_name' => "Event {$i}"]);
        }

        $this->submitRequest(['event_name' => 'Event 6'])->assertStatus(429);
        $this->assertSame(5, EventRequest::count());
    }

    public function test_returning_customers_are_matched_not_duplicated(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class); // many posts; throttling has its own test
        $this->submitRequest(['email' => 'jane@example.com', 'phone' => '08031234567']);
        $this->submitRequest(['email' => 'JANE@example.com', 'phone' => '+234 803 123 4567']);
        $this->submitRequest(['email' => 'other@example.com', 'phone' => '2348031234567']); // matched by phone

        $this->assertSame(1, Customer::count());
        $this->assertSame(3, Customer::first()->requests()->count());
        $this->assertFalse(Customer::first()->needs_review);

        $this->submitRequest(['email' => 'jane@example.com', 'contact_person' => 'Somebody Else', 'company' => 'Other Ltd']);
        $this->assertTrue(Customer::first()->fresh()->needs_review);
        $this->assertSame(1, Customer::count());
    }

    public function test_staff_are_notified_and_the_customer_gets_a_confirmation(): void
    {
        Notification::fake();
        Mail::fake();
        config(['mail.default' => 'smtp']);
        Settings::set('notifications.request_recipients', 'bookings@nebo.test');
        $ops = $this->userWithRole('Operations Manager');
        $crew = $this->userWithRole('Crew');

        $this->submitRequest();

        Notification::assertSentTo($ops, NewEventRequest::class);
        Notification::assertNotSentTo($crew, NewEventRequest::class);
        Notification::assertSentOnDemand(NewEventRequest::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === ['bookings@nebo.test']);
        Mail::assertQueued(RequestReceived::class, fn ($mail) => $mail->hasTo('jane@example.com'));
    }

    public function test_no_email_is_attempted_when_mail_is_not_configured(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);

        $this->submitRequest()->assertRedirect();

        Mail::assertNothingQueued();
    }

    public function test_design_files_are_stored_privately_when_the_customer_has_a_plan(): void
    {
        Storage::fake('local');

        $this->submitRequest([
            'has_existing_design' => 'yes',
            'files' => [UploadedFile::fake()->createWithContent('stage-plan.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"), UploadedFile::fake()->createWithContent('layout.dwg', "AC1032\0\0binary")],
        ])->assertSessionHasNoErrors();

        $request = EventRequest::firstOrFail();
        $this->assertCount(2, $request->documents);
        $doc = $request->documents->firstWhere('original_name', 'stage-plan.pdf');
        Storage::disk('local')->assertExists($doc->path);
        $this->assertStringNotContainsString('stage-plan', $doc->path);
        $this->assertSame('public_form', $doc->source);
        $this->assertSame(64, strlen($doc->checksum));
    }

    public function test_disguised_and_unsupported_files_are_rejected(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class); // many posts; throttling has its own test
        Storage::fake('local');

        $this->submitRequest(['has_existing_design' => 'yes', 'files' => [UploadedFile::fake()->createWithContent('plan.pdf', '<?php system($_GET["c"]); ?>')]])->assertSessionHasErrors('files.0');
        $this->submitRequest(['has_existing_design' => 'yes', 'files' => [UploadedFile::fake()->createWithContent('drawing.dwg', 'not really a drawing')]])->assertSessionHasErrors('files.0');
        $this->submitRequest(['has_existing_design' => 'yes', 'files' => [UploadedFile::fake()->create('run.exe', 10)]])->assertSessionHasErrors('files.0');
        $this->submitRequest(['has_existing_design' => 'yes', 'files' => array_map(fn ($i) => UploadedFile::fake()->createWithContent("p{$i}.pdf", "%PDF-1.4\n%%EOF"), range(1, 6))])->assertSessionHasErrors('files');

        $this->assertSame(0, EventRequest::count());
    }

    public function test_files_are_ignored_when_the_customer_says_they_have_no_plan(): void
    {
        Storage::fake('local');

        $this->submitRequest(['has_existing_design' => 'no', 'files' => [UploadedFile::fake()->createWithContent('p.pdf', "%PDF-1.4\n%%EOF")]])->assertSessionHasNoErrors();

        $this->assertCount(0, EventRequest::firstOrFail()->documents);
    }

    public function test_tracking_shows_a_simple_stage_and_never_internal_details(): void
    {
        $this->submitRequest();
        $request = EventRequest::firstOrFail();
        $staff = $this->userWithRole('Operations Manager', ['name' => 'Internal Staffer']);
        app(RequestWorkflow::class)->assign($staff, $request, $staff);
        app(RequestWorkflow::class)->addNote($staff, $request, 'Client is price sensitive');
        app(RequestWorkflow::class)->transition($staff, $request, RequestStatus::UnderReview);
        $this->post('/logout');

        $this->get(route('requests.track', $request->public_token))->assertOk()
            ->assertSee('Being reviewed')->assertSee($request->reference)
            ->assertDontSee('Client is price sensitive')->assertDontSee('Internal Staffer')->assertDontSee('Under Review');

        $this->post('/track', ['reference' => strtolower($request->reference), 'email' => 'JANE@example.com'])->assertRedirect(route('requests.track', $request->public_token));
        $this->post('/track', ['reference' => $request->reference, 'email' => 'wrong@example.com'])->assertSessionHasErrors('reference');
        $this->get('/track/not-a-token')->assertNotFound();
        $this->get('/track/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertNotFound();
    }

    public function test_home_page_lists_services_from_the_database_and_links_the_form(): void
    {
        Service::where('slug', 'barricades')->update(['is_active' => false]);

        $this->get('/')->assertOk()->assertSee(route('requests.create'))->assertSee('Event Lighting')->assertDontSee('Barricades');
    }
}
