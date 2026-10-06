<?php

namespace App\Services\Booking;

use App\Enums\RequestStatus;
use App\Mail\RequestReceived;
use App\Models\EventRequest;
use App\Models\StatusChange;
use App\Notifications\NewEventRequest;
use App\Services\Documents\DocumentStore;
use App\Services\ReferenceGenerator;
use App\Support\PhoneNumber;
use App\Support\Recipients;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a validated public form submission into a request: customer match,
 * reference number, services, files, history, and notifications. Idempotent
 * on the form's submission key, so a double submit returns the first request.
 * Notification failures never lose the request.
 */
class RequestIntake
{
    public function __construct(
        private CustomerMatcher $customers,
        private ReferenceGenerator $references,
        private DocumentStore $documents,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated form data
     * @param  list<UploadedFile>  $files
     * @param  array{ip?: ?string, user_agent?: ?string}  $meta
     */
    public function submit(array $data, array $files = [], array $meta = []): EventRequest
    {
        if (! empty($data['submission_key']) && $existing = EventRequest::where('submission_key', $data['submission_key'])->first()) {
            return $existing;
        }

        $request = DB::transaction(function () use ($data, $files, $meta) {
            $customer = $this->customers->findOrCreate([
                'name' => $data['contact_person'], 'company' => $data['company'] ?? null,
                'email' => $data['email'], 'phone' => $data['phone'],
            ]);

            $request = EventRequest::create([
                'reference' => $this->references->next('request'),
                'public_token' => (string) Str::ulid(),
                'submission_key' => $data['submission_key'] ?? null,
                'customer_id' => $customer->id,
                'event_name' => $data['event_name'],
                'event_type' => $data['event_type'],
                'event_type_other' => $data['event_type'] === 'other' ? ($data['event_type_other'] ?? null) : null,
                'event_date' => $data['event_date'],
                'venue' => $data['venue'],
                'contact_person' => $data['contact_person'],
                'company' => $data['company'] ?? null,
                'email' => mb_strtolower($data['email']),
                'phone' => PhoneNumber::normalize($data['phone']),
                'duration_days' => $data['duration_days'] ?? 1,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'setup_at' => $data['setup_at'] ?? null,
                'has_existing_design' => (bool) ($data['has_existing_design'] ?? false),
                'budget_range' => $data['budget_range'] ?? null,
                'requirements' => $data['requirements'],
                'additional_info' => $data['additional_info'] ?? null,
                'services_other' => in_array('other', $data['services'] ?? [], true) ? ($data['services_other'] ?? null) : null,
                'status' => RequestStatus::New,
                'submitted_ip' => $meta['ip'] ?? null,
                'user_agent' => isset($meta['user_agent']) ? Str::limit($meta['user_agent'], 250, '') : null,
            ]);

            $request->services()->sync(array_values(array_filter($data['services'] ?? [], 'is_numeric')));

            if ($request->has_existing_design) {
                foreach ($files as $file) {
                    $this->documents->store($file, $request, 'production_plan', 'public_form');
                }
            }

            StatusChange::create([
                'statusable_type' => $request->getMorphClass(), 'statusable_id' => $request->id,
                'from_status' => null, 'to_status' => RequestStatus::New->value,
                'user_name' => 'Customer (public form)', 'note' => 'Submitted online', 'created_at' => now(),
            ]);

            return $request;
        });

        $this->notify($request);

        return $request;
    }

    private function notify(EventRequest $request): void
    {
        try {
            Notification::send(Recipients::withPermission('requests.manage'), new NewEventRequest($request));

            $copies = array_filter(array_map('trim', explode(',', Settings::string('notifications.request_recipients'))));
            if ($copies && $this->mailConfigured()) {
                Notification::route('mail', $copies)->notify((new NewEventRequest($request))->viaMail());
            }

            if ($this->mailConfigured()) {
                Mail::to($request->email)->queue(new RequestReceived($request));
            }
        } catch (Throwable $e) {
            report($e); // the request is saved; a mail outage must not lose it
        }
    }

    /** Email goes out only when a real mailer is configured. */
    private function mailConfigured(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }
}
