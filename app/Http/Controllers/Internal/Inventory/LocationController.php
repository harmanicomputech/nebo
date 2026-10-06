<?php

namespace App\Http\Controllers\Internal\Inventory;

use App\Enums\StockBucket;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Support\Lookups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function __construct(private Lookups $lookups) {}

    public function index(): View
    {
        return view('internal.inventory.setup.locations', [
            'locations' => Location::withTrashed()
                ->withCount(['assets' => fn ($q) => $q->whereNull('deleted_at')])
                ->withSum(['stockLevels as stock_units' => fn ($q) => $q->where('bucket', StockBucket::Available->value)], 'quantity')
                ->orderBy('name')->get(),
            'types' => $this->lookups->options('location_type'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $location = Location::create($this->validated($request) + ['is_active' => true]);

        return back()->with('success', "Location {$location->name} added.");
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $location->update($this->validated($request, $location));

        return back()->with('success', 'Location saved.');
    }

    /** Only empty locations can be archived; history keeps pointing at them. */
    public function destroy(Location $location): RedirectResponse
    {
        $assets = $location->assets()->count();
        $stock = (int) $location->stockLevels()->sum('quantity');

        if ($assets > 0 || $stock > 0) {
            throw ValidationException::withMessages(['location' => "{$location->name} still holds {$assets} asset(s) and {$stock} stock unit(s). Move them first."]);
        }

        $location->update(['is_active' => false]);
        $location->delete();

        return back()->with('success', "Location {$location->name} archived.");
    }

    public function restore(int $id): RedirectResponse
    {
        $location = Location::onlyTrashed()->findOrFail($id);
        $location->restore();
        $location->update(['is_active' => true]);

        return back()->with('success', "Location {$location->name} restored.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Location $location = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-_]+$/', Rule::unique('locations', 'code')->ignore($location)],
            'type' => ['required', Rule::in(array_merge($this->lookups->activeKeys('location_type'), $location ? [$location->type] : []))],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ], ['code.regex' => 'Use capital letters, numbers, - and _ only.']);
    }
}
