<?php

namespace Tests\Feature;

use App\Enums\QuotationStatus;
use App\Enums\RequestStatus;
use App\Mail\QuotationReady;
use App\Models\Customer;
use App\Models\EventRequest;
use App\Models\Note;
use App\Models\ProductionPackage;
use App\Models\Quotation;
use App\Notifications\QuotationResponded;
use App\Services\Booking\RequestWorkflow;
use App\Services\Commercial\QuotationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithEvents;
use Tests\Concerns\SubmitsRequests;
use Tests\TestCase;

class CommercialTest extends TestCase
{
    use InteractsWithEvents, SubmitsRequests;

    /** @return array<string, mixed> */
    private function quotePayload(Customer $customer, array $overrides = []): array
    {
        return $overrides + [
            'customer_id' => $customer->id,
            'title' => 'Annual Gala',
            'valid_until' => now()->addDays(14)->toDateString(),
            'discount' => '10,000',
            'tax_percent' => '7.5',
            'items' => [
                ['section' => 'equipment', 'description' => 'Moving heads', 'quantity' => 8, 'days' => 2, 'unit_price' => '45,000'],
                ['section' => 'labour', 'description' => 'Crew', 'quantity' => 4, 'days' => 2, 'unit_price' => '30000'],
                ['section' => 'other', 'description' => '', 'quantity' => 1, 'days' => 1, 'unit_price' => '999'], // blank lines are dropped
            ],
        ];
    }

    private function draft(?Customer $customer = null, ?array $items = null): Quotation
    {
        return app(QuotationService::class)->create($this->superAdmin(), $customer ?? $this->customer(), [
            'title' => 'Gala', 'valid_until' => now()->addDays(14)->toDateString(),
            'items' => $items ?? [['section' => 'services', 'description' => 'Production', 'quantity' => 1, 'days' => 1, 'unit_price_kobo' => 100_000_00]],
        ]);
    }

    public function test_totals_are_computed_on_the_server(): void
    {
        $finance = $this->userWithRole('Finance / Commercial');
        $customer = $this->customer();

        $this->actingAs($finance)->post('/app/quotations', $this->quotePayload($customer, ['items' => [
            ['section' => 'equipment', 'description' => 'Moving heads', 'quantity' => 8, 'days' => 2, 'unit_price' => '45,000', 'line_total' => '1'],
            ['section' => 'labour', 'description' => 'Crew', 'quantity' => 4, 'days' => 2, 'unit_price' => '30000'],
            ['section' => 'other', 'description' => '', 'quantity' => 1, 'days' => 1, 'unit_price' => '999'],
        ], 'total' => '5']))->assertSessionHasNoErrors();

        $quote = Quotation::firstOrFail();
        // 8×2×45,000 + 4×2×30,000 = 960,000; −10,000 = 950,000; VAT 7.5% = 71,250; total 1,021,250.
        $this->assertSame([960_000_00, 10_000_00, 750, 71_250_00, 1_021_250_00], [$quote->subtotal_kobo, $quote->discount_kobo, $quote->tax_rate_bp, $quote->tax_kobo, $quote->total_kobo]);
        $this->assertSame(2, $quote->items()->count());
        $this->assertSame(QuotationStatus::Draft, $quote->status);
        $this->assertMatchesRegularExpression('/^NEBO-QUO-\d{4}-00001$/', $quote->reference);

        $this->actingAs($finance)->get("/app/quotations/{$quote->reference}")->assertOk()->assertSee('₦1,021,250');
        $this->actingAs($finance)->get("/app/quotations/{$quote->reference}/print")->assertOk()->assertSee('Moving heads');
    }

    public function test_sending_needs_approval_and_moves_the_request(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $this->submitRequest();
        $request = EventRequest::firstOrFail();
        $admin = $this->superAdmin();
        foreach ([RequestStatus::UnderReview, RequestStatus::QuotationPreparation] as $s) {
            app(RequestWorkflow::class)->transition($admin, $request->fresh(), $s);
        }
        $quote = app(QuotationService::class)->create($admin, $request->customer, ['title' => 'Gala', 'event_request_id' => $request->id, 'valid_until' => now()->addDays(10)->toDateString(),
            'items' => [['section' => 'services', 'description' => 'Production', 'quantity' => 1, 'days' => 1, 'unit_price_kobo' => 500_000_00]]]);

        // A sales role without quotations.approve can't send.
        $pm = $this->userWithRole('Production Manager');
        $this->actingAs($pm)->post("/app/quotations/{$quote->reference}/send")->assertForbidden();

        $finance = $this->userWithRole('Finance / Commercial');
        $this->actingAs($finance)->post("/app/quotations/{$quote->reference}/send")->assertSessionHasNoErrors();

        $quote->refresh();
        $this->assertSame(QuotationStatus::Sent, $quote->status);
        $this->assertNotNull($quote->sent_at);
        $this->assertSame(RequestStatus::QuotationSent, $request->fresh()->status);
        Mail::assertQueued(QuotationReady::class, fn ($m) => $m->hasTo($request->customer->email));

        // Sent quotations can't be edited without a revision.
        $this->actingAs($finance)->get("/app/quotations/{$quote->reference}/edit")->assertForbidden();
    }

    public function test_an_empty_quotation_cannot_be_sent(): void
    {
        $quote = $this->draft(items: []);
        $this->expectException(ValidationException::class);
        app(QuotationService::class)->send($this->superAdmin(), $quote);
    }

    public function test_customer_accepts_through_their_private_link(): void
    {
        Notification::fake();
        $quote = $this->draft();
        $admin = $this->superAdmin();
        $finance = $this->userWithRole('Finance / Commercial');

        // Drafts aren't visible to the customer.
        $this->get("/q/{$quote->public_token}")->assertNotFound();

        app(QuotationService::class)->send($admin, $quote);
        $this->get('/q/not-a-token')->assertNotFound();
        $this->get("/q/{$quote->public_token}")->assertOk()->assertSee('Production')->assertSee('Accept the quotation')->assertDontSee('Notes');
        $this->assertNotNull($quote->fresh()->viewed_at);

        $this->post("/q/{$quote->public_token}", ['answer' => 'accepted', 'name' => 'Ada'])->assertSessionHasErrors('agree');
        $this->post("/q/{$quote->public_token}", ['answer' => 'accepted', 'name' => 'Ada', 'agree' => '1'])->assertSessionHasNoErrors();

        $quote->refresh();
        $this->assertSame(QuotationStatus::Accepted, $quote->status);
        $this->assertSame('Ada', $quote->responded_by_name);
        Notification::assertSentTo($finance, QuotationResponded::class);

        // Answered: no second answer, and the page no longer offers the form.
        $this->post("/q/{$quote->public_token}", ['answer' => 'declined', 'name' => 'Ada'])->assertSessionHasErrors('status');
        $this->get("/q/{$quote->public_token}")->assertOk()->assertSee('Accepted by Ada')->assertDontSee('Accept the quotation');
    }

    public function test_revising_changes_the_link_and_expired_quotes_cannot_be_accepted(): void
    {
        $admin = $this->superAdmin();
        $service = app(QuotationService::class);
        $quote = $this->draft();
        $service->send($admin, $quote);
        $oldToken = $quote->public_token;

        $service->revise($admin, $quote->fresh());
        $quote->refresh();
        $this->assertSame([QuotationStatus::Draft, 2], [$quote->status, $quote->revision]);
        $this->assertNotSame($oldToken, $quote->public_token);
        $this->get("/q/{$oldToken}")->assertNotFound();

        $service->send($admin, $quote);
        $this->travel(20)->days();
        $this->post("/q/{$quote->public_token}", ['answer' => 'accepted', 'name' => 'Ada', 'agree' => '1'])->assertSessionHasErrors('status');
        $this->assertSame(1, $service->expireOverdue());
        $this->assertSame(QuotationStatus::Expired, $quote->fresh()->status);
    }

    public function test_packages_prefill_quotations(): void
    {
        $finance = $this->userWithRole('Finance / Commercial');
        $this->actingAs($finance)->post('/app/packages', ['name' => 'Conference AV', 'is_active' => '1', 'items' => [
            ['section' => 'services', 'description' => 'Production management', 'quantity' => 1, 'days' => 1, 'unit_price' => '450000'],
            ['section' => 'labour', 'description' => 'Crew', 'quantity' => 6, 'days' => 2, 'unit_price' => '35000'],
        ]])->assertSessionHasNoErrors();
        $package = ProductionPackage::firstOrFail();
        $this->assertSame('conference-av', $package->slug);
        $this->assertSame(870_000_00, $package->load('items')->totalKobo());

        $this->actingAs($finance)->get("/app/quotations/create?package={$package->id}")->assertOk()->assertSee('Production management');

        $quote = $this->draft();
        $this->actingAs($finance)->post("/app/quotations/{$quote->reference}/package", ['package_id' => $package->id])->assertSessionHasNoErrors();
        $this->assertSame(3, $quote->items()->count());
        $this->assertSame(970_000_00, $quote->fresh()->subtotal_kobo);

        $this->actingAs($this->userWithRole('Viewer'))->get('/app/packages')->assertForbidden();
    }

    public function test_merging_customers_moves_their_history(): void
    {
        $manager = $this->userWithRole('Finance / Commercial');
        $keep = Customer::create(['name' => 'Ada Obi', 'company' => 'Acme', 'email' => 'ada@acme.test']);
        $dupe = Customer::create(['name' => 'Ada O.', 'phone' => '+2348011112222', 'needs_review' => true, 'remarks' => 'Prefers WhatsApp']);
        $quote = $this->draft($dupe);
        Note::create(['notable_type' => $dupe->getMorphClass(), 'notable_id' => $dupe->id, 'body' => 'Called', 'user_name' => 'x']);
        $event = $this->event(10, 1, ['customer_id' => $dupe->id]);

        $this->actingAs($manager)->post("/app/customers/{$keep->id}/merge", ['duplicate_id' => $keep->id])->assertSessionHasErrors('duplicate_id');
        $this->actingAs($manager)->post("/app/customers/{$keep->id}/merge", ['duplicate_id' => $dupe->id])->assertSessionHasNoErrors();

        $keep->refresh();
        $this->assertSoftDeleted($dupe);
        $this->assertSame($keep->id, $quote->fresh()->customer_id);
        $this->assertSame($keep->id, $event->fresh()->customer_id);
        $this->assertSame('+2348011112222', $keep->phone);
        $this->assertStringContainsString('Prefers WhatsApp', $keep->remarks);
        $this->assertSame(1, $keep->notes()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'customer_merged', 'auditable_id' => (string) $keep->id]);

        $this->actingAs($manager)->get("/app/customers/{$keep->id}")->assertOk()->assertSee($quote->reference)->assertSee($event->name);
    }

    public function test_customer_access(): void
    {
        $customer = $this->customer();
        $this->actingAs($this->userWithRole('Viewer'))->get('/app/customers')->assertOk()->assertSee($customer->name);
        $this->actingAs($this->userWithRole('Viewer'))->get('/app/customers/create')->assertForbidden();
        $this->actingAs($this->userWithRole('Crew'))->get('/app/customers')->assertForbidden();
        $this->actingAs($this->userWithRole('Crew'))->get('/app/quotations')->assertForbidden();

        $finance = $this->userWithRole('Finance / Commercial');
        $this->actingAs($finance)->post('/app/customers', ['name' => 'Bola', 'email' => 'BOLA@Example.com', 'phone' => '0803 123 4567', 'type' => 'corporate'])->assertSessionHasNoErrors();
        $bola = Customer::where('name', 'Bola')->firstOrFail();
        $this->assertSame(['bola@example.com', '+2348031234567'], [$bola->email, $bola->phone]);
    }

    public function test_quotations_from_requests_start_with_the_requested_services(): void
    {
        $this->submitRequest();
        $request = EventRequest::with('services')->firstOrFail();
        $finance = $this->userWithRole('Finance / Commercial');

        $this->actingAs($finance)->get("/app/quotations/create?request={$request->id}")->assertOk()
            ->assertSee($request->event_name)->assertSee($request->services->first()->name);
        $this->actingAs($this->superAdmin())->get("/app/requests/{$request->id}")->assertOk()->assertSee('New quotation');
    }
}
