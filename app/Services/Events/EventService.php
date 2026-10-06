<?php

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Enums\RequestStatus;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\StatusChange;
use App\Models\User;
use App\Services\Booking\RequestWorkflow;
use App\Services\ReferenceGenerator;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventService
{
    public function __construct(private ReferenceGenerator $references, private RequestWorkflow $requests) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int|string>  $serviceIds
     */
    public function create(User $actor, array $data, array $serviceIds = []): Event
    {
        $this->assertWindow($data);

        return DB::transaction(function () use ($actor, $data, $serviceIds) {
            $event = Event::create(array_merge($data, [
                'reference' => $this->references->next('event'),
                'status' => EventStatus::Planning,
            ]));
            $event->services()->sync($serviceIds);

            StatusChange::create([
                'statusable_type' => $event->getMorphClass(), 'statusable_id' => $event->id,
                'from_status' => null, 'to_status' => EventStatus::Planning->value,
                'user_id' => $actor->id, 'user_name' => $actor->name,
                'note' => $event->event_request_id ? 'Created from request '.$event->request?->reference : 'Event created', 'created_at' => now(),
            ]);

            return $event;
        });
    }

    /**
     * Turns a won request into an event, once. An Approved request moves on
     * to Production Scheduled.
     *
     * @param  array<string, mixed>  $data
     */
    public function convert(User $actor, EventRequest $request, array $data): Event
    {
        if (! $request->status->isWon()) {
            throw ValidationException::withMessages(['request' => 'Only confirmed requests can become events. Move the request to Confirmed first.']);
        }

        if ($request->converted_event_id) {
            throw ValidationException::withMessages(['request' => 'This request already has an event.']);
        }

        $serviceIds = $data['services'] ?? $request->services()->pluck('services.id')->all();
        unset($data['services']);

        return DB::transaction(function () use ($actor, $request, $data, $serviceIds) {
            $event = $this->create($actor, array_merge([
                'event_request_id' => $request->id,
                'customer_id' => $request->customer_id,
                'event_type' => $request->event_type,
                'production_requirements' => trim($request->requirements."\n\n".($request->additional_info ?? '')),
            ], $data), $serviceIds);

            $request->update(['converted_event_id' => $event->id]);
            $request->documents()->get()->each(fn ($doc) => $doc->replicate()->fill([
                'documentable_type' => $event->getMorphClass(), 'documentable_id' => $event->id,
            ])->save());

            if ($request->status === RequestStatus::Approved) {
                $this->requests->transition($actor, $request, RequestStatus::ProductionScheduled, "Event {$event->reference} created");
            }

            Audit::record('converted', "Request {$request->reference} converted to event {$event->reference}", $event);

            return $event;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int|string>|null  $serviceIds
     */
    public function update(Event $event, array $data, ?array $serviceIds = null): Event
    {
        $this->assertWindow(array_merge($event->only(['setup_starts_at', 'starts_at', 'ends_at', 'breakdown_ends_at']), $data));

        DB::transaction(function () use ($event, $data, $serviceIds) {
            $event->update($data);
            if ($serviceIds !== null) {
                $event->services()->sync($serviceIds);
            }
        });

        return $event;
    }

    /**
     * Setup ≤ start < end ≤ breakdown end.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertWindow(array $data): void
    {
        $t = fn (string $k) => isset($data[$k]) ? strtotime((string) $data[$k]) : null;
        [$setup, $start, $end, $breakdown] = [$t('setup_starts_at'), $t('starts_at'), $t('ends_at'), $t('breakdown_ends_at')];

        $errors = array_filter([
            'setup_starts_at' => $setup && $start && $setup > $start ? 'Setup must start before the event.' : null,
            'ends_at' => $start && $end && $end <= $start ? 'The event must end after it starts.' : null,
            'breakdown_ends_at' => $end && $breakdown && $breakdown < $end ? 'Breakdown must finish after the event ends.' : null,
        ]);

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
