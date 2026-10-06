<?php

namespace App\Http\Controllers\Internal\Logistics;

use App\Enums\TripDirection;
use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Logistics\TripRequest;
use App\Models\Event;
use App\Models\Location;
use App\Models\LogisticsTrip;
use App\Models\Staff;
use App\Models\Vehicle;
use App\Services\Logistics\TripService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TripController extends Controller
{
    public function __construct(private TripService $trips) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', LogisticsTrip::class);
        $user = $request->user();
        $views = ['upcoming' => 'Upcoming', 'today' => 'Today', 'in_transit' => 'On the road', 'past' => 'Past'];
        $view = array_key_exists((string) $request->query('view'), $views) ? $request->query('view') : 'upcoming';
        $filters = $request->only(['q', 'direction', 'vehicle', 'driver']);
        $tz = config('nebo.display_timezone');
        $today = CarbonImmutable::now($tz)->startOfDay();

        $trips = LogisticsTrip::query()->visibleTo($user)
            ->search($filters['q'] ?? null)
            ->when($filters['direction'] ?? null, fn ($q, $d) => $q->where('direction', $d))
            ->when($filters['vehicle'] ?? null, fn ($q, $v) => $q->where('vehicle_id', $v))
            ->when($filters['driver'] ?? null, fn ($q, $d) => $q->where('driver_id', $d))
            ->when($view === 'upcoming', fn ($q) => $q->active()->orderBy('departs_at'))
            ->when($view === 'today', fn ($q) => $q->where('status', '!=', TripStatus::Cancelled)->where('departs_at', '<', $today->addDay()->utc())->where('arrives_at', '>=', $today->utc())->orderBy('departs_at'))
            ->when($view === 'in_transit', fn ($q) => $q->where('status', TripStatus::InTransit)->orderBy('arrives_at'))
            ->when($view === 'past', fn ($q) => $q->whereIn('status', [TripStatus::Arrived, TripStatus::Cancelled])->latest('departs_at'))
            ->with(['event', 'vehicle', 'driver'])->withCount(['items', 'crew'])
            ->paginate(25)->withQueryString();

        $visible = fn () => LogisticsTrip::query()->visibleTo($user);

        return view('internal.logistics.trips.index', [
            'trips' => $trips, 'view' => $view, 'views' => $views, 'filters' => $filters,
            'directions' => TripDirection::options(),
            'vehicles' => $user->can('logistics.view') ? Vehicle::options() : [],
            'drivers' => $user->can('logistics.view') ? Staff::options() : [],
            'stats' => [
                'today' => $visible()->where('status', '!=', TripStatus::Cancelled)->where('departs_at', '<', $today->addDay()->utc())->where('arrives_at', '>=', $today->utc())->count(),
                'in_transit' => $visible()->where('status', TripStatus::InTransit)->count(),
                'unassigned' => $visible()->active()->where(fn ($q) => $q->whereNull('vehicle_id')->orWhereNull('driver_id'))->count(),
                'vehicles' => $user->can('logistics.view') ? Vehicle::query()->where('status', 'active')->count() : null,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', LogisticsTrip::class);
        $direction = TripDirection::tryFrom((string) $request->query('direction')) ?? TripDirection::Outbound;
        $event = $request->query('event') ? Event::findOrFail($request->query('event')) : null;
        abort_if($event && ! $request->user()->can('view', $event), 403);

        $base = Location::query()->where('type', 'warehouse')->where('is_active', true)->orderBy('id')->first();
        $baseLabel = $base ? trim($base->name.($base->address ? ', '.$base->address : '')) : '';
        $trip = new LogisticsTrip(['direction' => $direction]);

        if ($event) {
            [$trip->origin, $trip->destination] = $direction === TripDirection::Return ? [$event->venue, $baseLabel] : [$baseLabel, $event->venue];
            [$trip->departs_at, $trip->arrives_at] = $direction === TripDirection::Return
                ? [$event->breakdown_ends_at, $event->breakdown_ends_at->copy()->addHours(3)]
                : [$event->setup_starts_at->copy()->subHours(3), $event->setup_starts_at];
        }

        $candidates = $event ? $this->trips->manifestCandidates($event, $direction) : collect();
        $onTrips = $this->onOtherTrips($candidates->modelKeys(), $direction);

        return view('internal.logistics.trips.form', $this->formData($trip) + [
            'event' => $event,
            'candidates' => $candidates,
            'onTrips' => $onTrips,
            'selected' => $candidates->modelKeys() ? array_values(array_diff($candidates->modelKeys(), array_keys($onTrips))) : [],
            'selectedCrew' => [],
        ]);
    }

    public function store(TripRequest $request): RedirectResponse
    {
        $event = $request->validated('event_id') ? Event::findOrFail($request->validated('event_id')) : null;
        abort_if($event && ! $request->user()->can('view', $event), 403);
        $trip = $this->trips->plan($request->user(), $event, $request->tripData());

        return redirect()->route('app.logistics.trips.show', $trip)->with('success', "Trip {$trip->reference} planned.");
    }

    public function show(LogisticsTrip $trip): View
    {
        $this->authorize('view', $trip);
        $trip->load(['event.customer', 'vehicle', 'driver', 'crew', 'items.asset.status', 'items.equipment', 'statusChanges', 'notes', 'documents']);

        return view('internal.logistics.trips.show', ['trip' => $trip]);
    }

    public function edit(LogisticsTrip $trip): View
    {
        $this->authorize('update', $trip);
        $trip->load(['event', 'crew', 'items']);
        $candidates = $trip->event ? $this->trips->manifestCandidates($trip->event, $trip->direction) : collect();

        return view('internal.logistics.trips.form', $this->formData($trip) + [
            'event' => $trip->event,
            'candidates' => $candidates,
            'onTrips' => $this->onOtherTrips($candidates->modelKeys(), $trip->direction, $trip),
            'selected' => $trip->items->modelKeys(),
            'selectedCrew' => $trip->crew->modelKeys(),
        ]);
    }

    public function update(TripRequest $request, LogisticsTrip $trip): RedirectResponse
    {
        $this->trips->update($request->user(), $trip, $request->tripData());

        return redirect()->route('app.logistics.trips.show', $trip)->with('success', 'Trip updated.');
    }

    public function transition(Request $request, LogisticsTrip $trip): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(TripStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'received_by' => ['nullable', 'string', 'max:120'],
        ]);
        $to = TripStatus::from($data['status']);
        $this->authorize($to === TripStatus::Cancelled || $to === TripStatus::Planned ? 'cancel' : 'progress', $trip);

        $this->trips->transition($request->user(), $trip, $to, $data['note'] ?? null, $data['received_by'] ?? null);

        return back()->with('success', "{$trip->reference} is now {$to->label()}.");
    }

    public function note(Request $request, LogisticsTrip $trip): RedirectResponse
    {
        $this->authorize('addNote', $trip);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $trip->notes()->create(['body' => $data['body'], 'user_id' => $request->user()->id, 'user_name' => $request->user()->name]);

        return back()->with('success', 'Note added.');
    }

    /**
     * Allocation id => reference of the other active trip carrying it the same way.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function onOtherTrips(array $ids, TripDirection $direction, ?LogisticsTrip $ignore = null): array
    {
        if (! $ids) {
            return [];
        }

        return DB::table('logistics_trip_items')->join('logistics_trips', 'logistics_trips.id', '=', 'logistics_trip_items.trip_id')
            ->whereIn('logistics_trip_items.allocation_id', $ids)->where('logistics_trips.direction', $direction->value)
            ->whereIn('logistics_trips.status', array_merge(TripStatus::activeValues(), [TripStatus::Arrived->value]))
            ->when($ignore, fn ($q) => $q->where('logistics_trips.id', '!=', $ignore->id))
            ->pluck('logistics_trips.reference', 'logistics_trip_items.allocation_id')->all();
    }

    /** @return array<string, mixed> */
    private function formData(LogisticsTrip $trip): array
    {
        return [
            'trip' => $trip,
            'vehicles' => Vehicle::options($trip->vehicle_id),
            'staff' => Staff::options($trip->driver_id),
            'directions' => TripDirection::options(),
        ];
    }
}
