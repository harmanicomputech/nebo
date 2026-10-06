<?php

namespace App\Http\Controllers\Internal\Logistics;

use App\Enums\TripStatus;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Logistics\VehicleRequest;
use App\Models\Location;
use App\Models\Staff;
use App\Models\Vehicle;
use App\Services\Logistics\VehicleService;
use App\Support\Lookups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function __construct(private VehicleService $vehicles, private Lookups $lookups) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Vehicle::class);
        $filters = $request->only(['q', 'type', 'status', 'archived']);

        $vehicles = Vehicle::query()
            ->when($filters['archived'] ?? false, fn ($q) => $q->onlyTrashed())
            ->search($filters['q'] ?? null)
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->with(['defaultDriver', 'baseLocation'])
            ->withCount(['trips as upcoming_trips_count' => fn ($q) => $q->whereIn('status', TripStatus::activeValues())])
            ->withExists(['trips as on_road' => fn ($q) => $q->where('status', TripStatus::InTransit)])
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('internal.logistics.vehicles.index', [
            'vehicles' => $vehicles, 'filters' => $filters,
            'types' => $this->lookups->options('vehicle_type'), 'statuses' => VehicleStatus::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Vehicle::class);

        return view('internal.logistics.vehicles.form', $this->formData(new Vehicle(['status' => VehicleStatus::Active, 'type' => 'truck'])));
    }

    public function store(VehicleRequest $request): RedirectResponse
    {
        $vehicle = $this->vehicles->save(null, $request->validated());

        return redirect()->route('app.logistics.vehicles.show', $vehicle)->with('success', "{$vehicle->name} added.");
    }

    public function show(Vehicle $vehicle): View
    {
        $this->authorize('view', $vehicle);
        $vehicle->load(['defaultDriver', 'baseLocation', 'documents', 'notes']);

        return view('internal.logistics.vehicles.show', [
            'vehicle' => $vehicle,
            'upcoming' => $vehicle->trips()->whereIn('status', TripStatus::activeValues())->with(['event', 'driver'])->orderBy('departs_at')->get(),
            'past' => $vehicle->trips()->whereNotIn('status', TripStatus::activeValues())->with(['event', 'driver'])->latest('departs_at')->limit(15)->get(),
        ]);
    }

    public function edit(Vehicle $vehicle): View
    {
        $this->authorize('update', $vehicle);

        return view('internal.logistics.vehicles.form', $this->formData($vehicle));
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $this->vehicles->save($vehicle, $request->validated());

        return redirect()->route('app.logistics.vehicles.show', $vehicle)->with('success', 'Vehicle saved.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('delete', $vehicle);
        $this->vehicles->archive($vehicle);

        return redirect()->route('app.logistics.vehicles.index')->with('success', "{$vehicle->name} archived.");
    }

    public function restore(int $id): RedirectResponse
    {
        $vehicle = Vehicle::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $vehicle);
        $vehicle->restore();

        return redirect()->route('app.logistics.vehicles.show', $vehicle)->with('success', "{$vehicle->name} restored.");
    }

    public function note(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('update', $vehicle);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $vehicle->notes()->create(['body' => $data['body'], 'user_id' => $request->user()->id, 'user_name' => $request->user()->name]);

        return back()->with('success', 'Note added.');
    }

    /** @return array<string, mixed> */
    private function formData(Vehicle $vehicle): array
    {
        return [
            'vehicle' => $vehicle,
            'types' => $this->lookups->options('vehicle_type', $vehicle->type),
            'statuses' => VehicleStatus::options(),
            'drivers' => Staff::options($vehicle->default_driver_id),
            'locations' => Location::options($vehicle->base_location_id),
        ];
    }
}
