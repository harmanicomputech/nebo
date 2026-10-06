<?php

namespace App\Services\Commercial;

use App\Enums\QuotationStatus;
use App\Enums\QuoteSection;
use App\Enums\RequestStatus;
use App\Mail\QuotationReady;
use App\Models\Customer;
use App\Models\ProductionPackage;
use App\Models\Quotation;
use App\Models\StatusChange;
use App\Models\User;
use App\Notifications\QuotationResponded;
use App\Services\Booking\RequestWorkflow;
use App\Services\ReferenceGenerator;
use App\Support\Audit\Audit;
use App\Support\Recipients;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Quotations (D60–D62). Totals are always computed here from the lines,
 * never taken from the browser:
 *   line   = quantity × days × unit price
 *   tax    = round((subtotal − discount) × rate), discount capped at subtotal
 *   total  = subtotal − discount + tax
 *
 * $data keys: title, valid_until, discount_kobo, tax_rate_bp, intro, terms,
 * event_request_id, event_id, items (list of section, description,
 * quantity, days, unit_price_kobo, service_id?, equipment_id?).
 */
class QuotationService
{
    public function __construct(private ReferenceGenerator $references) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, Customer $customer, array $data): Quotation
    {
        return DB::transaction(function () use ($actor, $customer, $data) {
            $quote = Quotation::create([
                'reference' => $this->references->next('quotation'),
                'public_token' => (string) Str::ulid(),
                'customer_id' => $customer->id,
                'event_request_id' => $data['event_request_id'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'title' => $data['title'],
                'status' => QuotationStatus::Draft,
                'valid_until' => $data['valid_until'],
                'discount_kobo' => $data['discount_kobo'] ?? 0,
                'tax_rate_bp' => $data['tax_rate_bp'] ?? self::defaultTaxRateBp(),
                'intro' => $data['intro'] ?? null,
                'terms' => $data['terms'] ?? Settings::string('quotations.terms'),
                'prepared_by' => $actor->id,
            ]);

            $this->replaceItems($quote, $data['items'] ?? []);
            $this->history($actor, $quote, null, QuotationStatus::Draft);

            return $quote;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Quotation $quote, array $data): void
    {
        $this->assertDraft($quote);

        DB::transaction(function () use ($quote, $data) {
            $quote->update(collect($data)->only(['title', 'valid_until', 'discount_kobo', 'tax_rate_bp', 'intro', 'terms', 'event_request_id', 'event_id'])->all());
            $this->replaceItems($quote, $data['items'] ?? []);
        });
    }

    /** Appends a package's lines to a draft. */
    public function addPackage(Quotation $quote, ProductionPackage $package): void
    {
        $this->assertDraft($quote);
        $package->loadMissing('items');

        $lines = $quote->items()->get()->map(fn ($i) => $i->only(['section', 'description', 'service_id', 'equipment_id', 'quantity', 'days', 'unit_price_kobo']))->all();
        foreach ($package->items as $item) {
            $lines[] = $item->only(['section', 'description', 'service_id', 'equipment_id', 'quantity', 'days', 'unit_price_kobo']);
        }

        DB::transaction(fn () => $this->replaceItems($quote, $lines));
    }

    /** Approve and send: needs quotations.approve (checked by the policy). */
    public function send(User $actor, Quotation $quote): void
    {
        $this->assertCan($quote, QuotationStatus::Sent);
        $quote->loadMissing(['items', 'customer', 'request']);

        if ($quote->items->isEmpty() || $quote->total_kobo <= 0) {
            throw ValidationException::withMessages(['status' => 'Add at least one priced line before sending.']);
        }
        if ($quote->valid_until->lt(now(config('nebo.display_timezone'))->startOfDay())) {
            throw ValidationException::withMessages(['valid_until' => 'The validity date has passed. Revise the quotation and set a new date.']);
        }

        DB::transaction(function () use ($actor, $quote) {
            $from = $quote->status;
            $quote->update(['status' => QuotationStatus::Sent, 'issued_on' => now(config('nebo.display_timezone'))->toDateString(), 'sent_at' => now(), 'sent_by' => $actor->id]);
            $this->history($actor, $quote, $from, QuotationStatus::Sent);
            $this->moveRequest($actor, $quote, RequestStatus::QuotationSent, "Quotation {$quote->reference} sent");
        });

        if ($quote->customer->email && ! in_array(config('mail.default'), ['log', 'array', null], true)) {
            Mail::to($quote->customer->email)->queue(new QuotationReady($quote));
        }
    }

    /**
     * The customer's answer: online through their link ($actor null), or
     * recorded by staff after a call or email.
     */
    public function respond(?User $actor, Quotation $quote, bool $accepted, string $name, ?string $note = null): void
    {
        if ($quote->isExpired()) {
            throw ValidationException::withMessages(['status' => 'This quotation has expired. Ask us for an updated one.']);
        }

        $to = $accepted ? QuotationStatus::Accepted : QuotationStatus::Declined;
        $this->assertCan($quote, $to);
        $by = $actor ?? $quote->sender ?? $quote->preparer;

        DB::transaction(function () use ($actor, $quote, $to, $name, $note, $by, $accepted) {
            $from = $quote->status;
            $quote->update(['status' => $to, 'responded_at' => now(), 'responded_by_name' => $name, 'response_note' => $note]);
            $this->history($actor, $quote, $from, $to, trim(($actor ? 'Recorded for' : 'Online by').' '.$name.($note ? ": {$note}" : '')), $actor ? null : $name);

            if ($accepted && $by) {
                $this->moveRequest($by, $quote, RequestStatus::Confirmed, "Quotation {$quote->reference} accepted by {$name}");
            }
        });

        $recipients = Recipients::withPermission('quotations.approve')->push($quote->preparer)->filter()->unique('id')
            ->reject(fn ($u) => $actor && $u->is($actor));
        Notification::send($recipients, new QuotationResponded($quote));
    }

    /** Back to draft for changes; the revision number goes up and the old link stops accepting answers. */
    public function revise(User $actor, Quotation $quote): void
    {
        $this->assertCan($quote, QuotationStatus::Draft);

        DB::transaction(function () use ($actor, $quote) {
            $from = $quote->status;
            $quote->update(['status' => QuotationStatus::Draft, 'revision' => $quote->revision + 1, 'sent_at' => null, 'sent_by' => null, 'viewed_at' => null,
                'responded_at' => null, 'responded_by_name' => null, 'response_note' => null, 'public_token' => (string) Str::ulid()]);
            $this->history($actor, $quote, $from, QuotationStatus::Draft, "Revision {$quote->revision}");
        });
    }

    public function cancel(User $actor, Quotation $quote, string $reason): void
    {
        $this->assertCan($quote, QuotationStatus::Cancelled);
        if (blank($reason)) {
            throw ValidationException::withMessages(['note' => 'Give a reason for cancelling.']);
        }

        DB::transaction(function () use ($actor, $quote, $reason) {
            $from = $quote->status;
            $quote->update(['status' => QuotationStatus::Cancelled]);
            $this->history($actor, $quote, $from, QuotationStatus::Cancelled, $reason);
        });
    }

    /** A new draft with the same lines, for a new date or customer variation. */
    public function duplicate(User $actor, Quotation $quote): Quotation
    {
        $quote->loadMissing(['items', 'customer']);

        return $this->create($actor, $quote->customer, [
            'title' => $quote->title, 'valid_until' => now(config('nebo.display_timezone'))->addDays((int) Settings::get('quotations.validity_days'))->toDateString(),
            'discount_kobo' => $quote->discount_kobo, 'tax_rate_bp' => $quote->tax_rate_bp, 'intro' => $quote->intro, 'terms' => $quote->terms,
            'event_request_id' => $quote->event_request_id, 'event_id' => $quote->event_id,
            'items' => $quote->items->map(fn ($i) => $i->only(['section', 'description', 'service_id', 'equipment_id', 'quantity', 'days', 'unit_price_kobo']))->all(),
        ]);
    }

    /** Sent quotations past their validity become Expired (daily). */
    public function expireOverdue(): int
    {
        $today = now(config('nebo.display_timezone'))->toDateString();
        $count = 0;

        Quotation::query()->where('status', QuotationStatus::Sent)->whereDate('valid_until', '<', $today)->get()
            ->each(function (Quotation $quote) use (&$count) {
                $quote->update(['status' => QuotationStatus::Expired]);
                $this->history(null, $quote, QuotationStatus::Sent, QuotationStatus::Expired, 'Validity date passed');
                $count++;
            });

        return $count;
    }

    /** First open of the customer link. */
    public function markViewed(Quotation $quote): void
    {
        if (! $quote->viewed_at && $quote->status === QuotationStatus::Sent) {
            $quote->forceFill(['viewed_at' => now()])->saveQuietly();
        }
    }

    public static function defaultTaxRateBp(): int
    {
        return (int) round(((float) Settings::get('quotations.vat_percent')) * 100);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function replaceItems(Quotation $quote, array $lines): void
    {
        $quote->items()->delete();
        $subtotal = 0;

        foreach (array_values($lines) as $i => $line) {
            $quantity = max(1, (int) ($line['quantity'] ?? 1));
            $days = max(1, (int) ($line['days'] ?? 1));
            $unit = max(0, (int) ($line['unit_price_kobo'] ?? 0));
            $total = $quantity * $days * $unit;
            $subtotal += $total;

            $quote->items()->create([
                'section' => $line['section'] instanceof QuoteSection ? $line['section'] : (QuoteSection::tryFrom((string) ($line['section'] ?? '')) ?? QuoteSection::Other),
                'description' => Str::limit(trim((string) $line['description']), 255, ''),
                'service_id' => $line['service_id'] ?? null,
                'equipment_id' => $line['equipment_id'] ?? null,
                'quantity' => $quantity, 'days' => $days, 'unit_price_kobo' => $unit, 'line_total_kobo' => $total, 'sort_order' => $i,
            ]);
        }

        // The requested discount is kept; it never takes the total below zero.
        $discount = $quote->effectiveDiscountKobo($subtotal);
        $tax = (int) round(($subtotal - $discount) * $quote->tax_rate_bp / 10000);
        $quote->update(['subtotal_kobo' => $subtotal, 'tax_kobo' => $tax, 'total_kobo' => $subtotal - $discount + $tax]);
    }

    private function moveRequest(User $actor, Quotation $quote, RequestStatus $to, string $note): void
    {
        $request = $quote->request()->first();
        if ($request && $request->status->canMoveTo($to)) {
            app(RequestWorkflow::class)->transition($actor, $request, $to, $note);
        }
    }

    private function assertDraft(Quotation $quote): void
    {
        if ($quote->status !== QuotationStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Only drafts can be changed. Revise the quotation first.']);
        }
    }

    private function assertCan(Quotation $quote, QuotationStatus $to): void
    {
        if (! $quote->status->canMoveTo($to)) {
            throw ValidationException::withMessages(['status' => "A quotation that is {$quote->status->label()} can't move to {$to->label()}."]);
        }
    }

    private function history(?User $actor, Quotation $quote, ?QuotationStatus $from, QuotationStatus $to, ?string $note = null, ?string $name = null): void
    {
        StatusChange::create([
            'statusable_type' => $quote->getMorphClass(), 'statusable_id' => $quote->id,
            'from_status' => $from?->value, 'to_status' => $to->value,
            'user_id' => $actor?->id, 'user_name' => $actor?->name ?? $name ?? 'System', 'note' => $note, 'created_at' => now(),
        ]);

        if ($from) {
            Audit::record('status_changed', "Quotation {$quote->reference}: {$from->label()} → {$to->label()}", $quote, ['status' => $from->value], ['status' => $to->value]);
        }
    }
}
