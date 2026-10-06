<?php

namespace App\Services\Logistics;

use App\Enums\AllocationState;
use App\Enums\EventStatus;
use App\Enums\InventoryTransactionType as T;
use App\Enums\TripDirection;
use App\Enums\TripStatus;
use App\Enums\VehicleStatus;
use App\Models\AssetStatus;
use App\Models\EquipmentAllocation;
use App\Models\Event;
use App\Models\LogisticsTrip;
use App\Models\Staff;
use App\Models\StatusChange;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\TripAssigned;
use App\Services\Inventory\InventoryLedger;
use App\Services\ReferenceGenerator;
use App\Support\Audit\Audit;
use App\Support\Format;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Trips (D54–D57). Vehicle clashes are refused outright; a driver or crew
 * member already on an overlapping trip needs an override reason, which is
 * audited. Departure and arrival move the manifest's units through In
 * Transit to On Site (or Deployed if the event is live), with ledger entries.
 *
 * $data keys: direction, vehicle_id, driver_id, origin, destination,
 * departs_at, arrives_at (UTC Carbon), instructions, crew (staff ids),
 * items (allocation ids), override_reason.
 */
class TripService
{
    public function __construct(private ReferenceGenerator $references, private InventoryLedger $ledger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function plan(User $actor, ?Event $event, array $data): LogisticsTrip
    {
        $direction = TripDirection::from($data['direction']);
        $this->validate($event, $direction, $data);

        $trip = DB::transaction(function () use ($actor, $event, $direction, $data) {
            $trip = LogisticsTrip::create([
                'reference' => $this->references->next('trip'),
                'event_id' => $event?->id,
                'direction' => $direction,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'driver_id' => $data['driver_id'] ?? null,
                'origin' => $data['origin'],
                'destination' => $data['destination'],
                'departs_at' => $data['departs_at'],
                'arrives_at' => $data['arrives_at'],
                'status' => TripStatus::Planned,
                'instructions' => $data['instructions'] ?? null,
                'created_by' => $actor->id,
            ]);

            $trip->crew()->sync($data['crew'] ?? []);
            $trip->items()->sync($data['items'] ?? []);
            $this->history($actor, $trip, null, TripStatus::Planned);
            $this->auditOverride($trip, $data);

            return $trip;
        });

        $this->notifyPeople($trip, $actor, []);

        return $trip;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, LogisticsTrip $trip, array $data): void
    {
        if (! in_array($trip->status, [TripStatus::Planned, TripStatus::Loading], true)) {
            throw ValidationException::withMessages(['status' => "A trip that is {$trip->status->label()} can't be changed."]);
        }

        $data['direction'] = $trip->direction->value;
        $this->validate($trip->event, $trip->direction, $data, $trip);
        $before = $this->people($trip);

        DB::transaction(function () use ($trip, $data) {
            $trip->update(collect($data)->only(['vehicle_id', 'driver_id', 'origin', 'destination', 'departs_at', 'arrives_at', 'instructions'])->all());
            $trip->crew()->sync($data['crew'] ?? []);
            $trip->items()->sync($data['items'] ?? []);
            $this->auditOverride($trip, $data);
        });

        $this->notifyPeople($trip->refresh(), $actor, $before);
    }

    public function transition(User $actor, LogisticsTrip $trip, TripStatus $to, ?string $note = null, ?string $receivedBy = null): void
    {
        $from = $trip->status;

        if (! $from->canMoveTo($to)) {
            throw ValidationException::withMessages(['status' => "A trip that is {$from->label()} can't move to {$to->label()}."]);
        }

        if ($to === TripStatus::Cancelled && blank($note)) {
            throw ValidationException::withMessages(['note' => 'Give a reason for cancelling the trip.']);
        }

        $items = $trip->items()->with(['asset.status', 'equipment', 'event'])->get();

        if ($to === TripStatus::InTransit) {
            if (! $trip->vehicle_id || ! $trip->driver_id) {
                throw ValidationException::withMessages(['status' => 'Assign a vehicle and a driver before the trip leaves.']);
            }

            $notOut = $items->filter(fn (EquipmentAllocation $a) => $a->state !== AllocationState::CheckedOut);
            if ($notOut->isNotEmpty()) {
                throw ValidationException::withMessages(['status' => $notOut->count().' item(s) on the manifest haven\'t been dispatched from the load list. Dispatch them first, or take them off this trip.']);
            }
        }

        DB::transaction(function () use ($actor, $trip, $from, $to, $note, $receivedBy, $items) {
            $changes = ['status' => $to];

            if ($to === TripStatus::InTransit) {
                $changes['departed_at'] = now();
                $this->moveUnits($trip, $items, 'in_transit', T::InTransit, "Left on {$trip->reference}");
            }

            if ($to === TripStatus::Arrived) {
                $changes['arrived_at'] = now();
                $changes['received_by'] = $receivedBy;

                if ($trip->direction === TripDirection::Outbound) {
                    $status = $trip->event?->status === EventStatus::InProgress ? 'deployed' : 'on_site';
                    $this->moveUnits($trip, $items, $status, T::Delivered, "Delivered by {$trip->reference}".($receivedBy ? ", received by {$receivedBy}" : ''));
                } elseif ($trip->direction === TripDirection::Return) {
                    $this->moveUnits($trip, $items, 'checked_out', T::Delivered, "Back at base on {$trip->reference}; awaiting check-in");
                }
            }

            $trip->update($changes);
            $this->history($actor, $trip, $from, $to, $note);
        });
    }

    /**
     * Allocations an event can put on a trip in this direction, with the
     * active trip each is already on (if any).
     *
     * @return Collection<int, EquipmentAllocation>
     */
    public function manifestCandidates(Event $event, TripDirection $direction): Collection
    {
        $states = $direction === TripDirection::Return ? [AllocationState::CheckedOut] : [AllocationState::Reserved, AllocationState::CheckedOut];

        return $event->allocations()->whereIn('state', $states)->with(['asset', 'equipment'])->orderBy('equipment_id')->orderBy('asset_id')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validate(?Event $event, TripDirection $direction, array &$data, ?LogisticsTrip $ignore = null): void
    {
        /** @var CarbonInterface $from */
        $from = $data['departs_at'];
        /** @var CarbonInterface $to */
        $to = $data['arrives_at'];

        if ($to->lte($from)) {
            throw ValidationException::withMessages(['arrives_at' => 'Arrival must be after departure.']);
        }

        if ($direction !== TripDirection::Transfer && ! $event) {
            throw ValidationException::withMessages(['event_id' => 'Trips to or from a venue belong to an event.']);
        }

        if ($event && ! $ignore && ! $event->status->holdsResources()) {
            throw ValidationException::withMessages(['event_id' => "{$event->name} is {$event->status->label()}; trips can't be planned for it."]);
        }

        if ($vehicleId = $data['vehicle_id'] ?? null) {
            $vehicle = Vehicle::findOrFail($vehicleId);
            if ($vehicle->status !== VehicleStatus::Active && $vehicle->id !== $ignore?->vehicle_id) {
                throw ValidationException::withMessages(['vehicle_id' => "{$vehicle->name} is {$vehicle->status->label()}."]);
            }

            $clash = LogisticsTrip::query()->overlapping($from, $to)->where('vehicle_id', $vehicle->id)
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->orderBy('departs_at')->first();
            if ($clash) {
                throw ValidationException::withMessages(['vehicle_id' => "{$vehicle->name} is on {$clash->reference} ({$clash->destination}, ".Format::datetime($clash->departs_at, 'D j M, g:ia').' – '.Format::datetime($clash->arrives_at, 'g:ia').').']);
            }
        }

        // People on another overlapping trip need an override reason.
        $people = array_values(array_unique(array_filter(array_merge([$data['driver_id'] ?? null], $data['crew'] ?? []))));
        $busy = $people ? $this->busyStaff($people, $from, $to, $ignore) : collect();
        if ($busy->isNotEmpty() && blank($data['override_reason'] ?? null)) {
            throw ValidationException::withMessages(['override_reason' => $busy->map(fn ($trip, $name) => "{$name} is on {$trip->reference}")->implode('; ').'. Give a reason to book them anyway.']);
        }
        $data['_overridden'] = $busy->keys()->all();

        // Manifest: the event's own allocations, not already travelling the same way.
        $items = array_values(array_unique(array_map('intval', $data['items'] ?? [])));
        $data['items'] = $items;
        if ($items) {
            $allowed = $event ? $this->manifestCandidates($event, $direction)->modelKeys() : [];
            if (array_diff($items, $allowed)) {
                throw ValidationException::withMessages(['items' => $direction === TripDirection::Return
                    ? 'Only equipment that is out at the event can go on a return trip.'
                    : 'Only equipment booked for this event can go on the trip.']);
            }

            $taken = DB::table('logistics_trip_items')->join('logistics_trips', 'logistics_trips.id', '=', 'logistics_trip_items.trip_id')
                ->whereIn('logistics_trip_items.allocation_id', $items)->where('logistics_trips.direction', $direction->value)
                ->whereIn('logistics_trips.status', array_merge(TripStatus::activeValues(), [TripStatus::Arrived->value]))
                ->when($ignore, fn ($q) => $q->where('logistics_trips.id', '!=', $ignore->id))
                ->pluck('logistics_trips.reference')->unique();
            if ($taken->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'Some of these items are already on '.$taken->implode(', ').'.']);
            }
        }
    }

    /**
     * @param  list<int>  $staffIds
     * @return Collection<string, LogisticsTrip> staff name => clashing trip
     */
    private function busyStaff(array $staffIds, CarbonInterface $from, CarbonInterface $to, ?LogisticsTrip $ignore): Collection
    {
        $trips = LogisticsTrip::query()->overlapping($from, $to)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->where(fn ($q) => $q->whereIn('driver_id', $staffIds)->orWhereHas('crew', fn ($c) => $c->whereIn('staff.id', $staffIds)))
            ->with('crew')->get();

        $busy = collect();
        foreach (Staff::withTrashed()->whereIn('id', $staffIds)->get() as $staff) {
            $trip = $trips->first(fn (LogisticsTrip $t) => $t->driver_id === $staff->id || $t->crew->contains('id', $staff->id));
            if ($trip) {
                $busy[$staff->name] = $trip;
            }
        }

        return $busy;
    }

    /**
     * @param  Collection<int, EquipmentAllocation>  $items
     */
    private function moveUnits(LogisticsTrip $trip, Collection $items, string $statusCode, T $type, string $note): void
    {
        $status = AssetStatus::byCode($statusCode);

        foreach ($items->where('state', AllocationState::CheckedOut) as $allocation) {
            $meta = ['event_id' => $allocation->event_id, 'note' => $note];

            if ($allocation->asset) {
                $from = $allocation->asset->status_id;
                $allocation->asset->forceFill(['status_id' => $status->id])->save();
                $this->ledger->record($type, $allocation->equipment, $allocation->asset, $meta + ['from_status_id' => $from, 'to_status_id' => $status->id]);
            } else {
                $this->ledger->record($type, $allocation->equipment, null, $meta + ['quantity' => $allocation->quantity]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function auditOverride(LogisticsTrip $trip, array $data): void
    {
        if (! empty($data['_overridden'])) {
            Audit::record('trip_double_booked', "{$trip->reference}: booked ".implode(', ', $data['_overridden']).' despite another trip. Reason: '.$data['override_reason'], $trip);
        }
    }

    private function history(User $actor, LogisticsTrip $trip, ?TripStatus $from, TripStatus $to, ?string $note = null): void
    {
        StatusChange::create([
            'statusable_type' => $trip->getMorphClass(), 'statusable_id' => $trip->id,
            'from_status' => $from?->value, 'to_status' => $to->value,
            'user_id' => $actor->id, 'user_name' => $actor->name, 'note' => $note, 'created_at' => now(),
        ]);

        if ($from) {
            Audit::record('status_changed', "Trip {$trip->reference}: {$from->label()} → {$to->label()}", $trip, ['status' => $from->value], ['status' => $to->value]);
        }
    }

    /** @return list<int> user ids of the driver and crew */
    private function people(LogisticsTrip $trip): array
    {
        $staff = $trip->crew()->get()->push($trip->driver()->first())->filter();

        return $staff->pluck('user_id')->filter()->unique()->values()->all();
    }

    /**
     * @param  list<int>  $before
     */
    private function notifyPeople(LogisticsTrip $trip, User $actor, array $before): void
    {
        $new = array_diff($this->people($trip), $before, [$actor->id]);

        User::query()->active()->whereIn('id', $new)->get()
            ->each(fn (User $u) => $u->notify(new TripAssigned($trip)));
    }
}
