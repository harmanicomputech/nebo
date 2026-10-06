<?php

namespace App\Http\Controllers\Internal\Inventory;

use App\Enums\StockBucket;
use App\Enums\TrackingMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\EquipmentRequest;
use App\Models\AssetStatus;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Services\Inventory\EquipmentService;
use App\Support\ImageStore;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentController extends Controller
{
    public function __construct(private EquipmentService $service, private Lookups $lookups) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Equipment::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'mode' => ['nullable', 'in:serialized,bulk'],
            'status' => ['nullable', 'integer'],
            'location' => ['nullable', 'integer'],
            'condition' => ['nullable', 'string', 'max:50'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'availability' => ['nullable', 'in:available,none,low'],
            'archived' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:name,sku,available,updated'],
            'view' => ['nullable', 'in:table,grid'],
        ]);

        [$availableSql, $bindings] = Equipment::availableUnitsSql();

        $query = Equipment::query()
            ->with('category.parent')
            ->withAvailability()
            ->when($filters['archived'] ?? false, fn (Builder $q) => $q->onlyTrashed())
            ->search($filters['q'] ?? null)
            ->when($filters['category'] ?? null, function (Builder $q, $id) {
                $category = EquipmentCategory::withTrashed()->find($id);
                $q->whereIn('category_id', $category ? $category->selfAndChildIds() : [0]);
            })
            ->when($filters['mode'] ?? null, fn (Builder $q, $mode) => $q->where('tracking_mode', $mode))
            ->when($filters['manufacturer'] ?? null, fn (Builder $q, $m) => $q->where('manufacturer', $m))
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->whereHas('assets', fn ($a) => $a->where('status_id', $s)))
            ->when($filters['condition'] ?? null, fn (Builder $q, $c) => $q->whereHas('assets', fn ($a) => $a->where('condition', $c)))
            ->when($filters['location'] ?? null, fn (Builder $q, $l) => $q->where(fn ($w) => $w
                ->whereHas('assets', fn ($a) => $a->where('location_id', $l))
                ->orWhereHas('stockLevels', fn ($s) => $s->where('location_id', $l)->where('quantity', '>', 0))))
            ->when(($filters['availability'] ?? null) === 'available', fn (Builder $q) => $q->whereRaw("$availableSql > 0", $bindings))
            ->when(($filters['availability'] ?? null) === 'none', fn (Builder $q) => $q->whereRaw("$availableSql = 0", $bindings))
            ->when(($filters['availability'] ?? null) === 'low', fn (Builder $q) => $q->whereNotNull('low_stock_threshold')->whereRaw("$availableSql < equipment.low_stock_threshold", $bindings));

        match ($filters['sort'] ?? 'name') {
            'sku' => $query->orderBy('sku'),
            'available' => $query->orderByRaw("$availableSql desc", $bindings)->orderBy('name'),
            'updated' => $query->latest('updated_at'),
            default => $query->orderBy('name'),
        };

        $view = $filters['view'] ?? 'table';

        return view('internal.inventory.equipment.index', [
            'equipment' => $query->paginate($view === 'grid' ? 24 : 25)->withQueryString(),
            'filters' => $filters,
            'view' => $view,
            'categories' => EquipmentCategory::options(),
            'statuses' => AssetStatus::ordered()->pluck('label', 'id')->all(),
            'locations' => Location::options(),
            'conditions' => $this->lookups->options('condition'),
            'manufacturers' => Equipment::query()->whereNotNull('manufacturer')->distinct()->orderBy('manufacturer')->pluck('manufacturer', 'manufacturer')->all(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Equipment::class);

        return view('internal.inventory.equipment.form', $this->formData(new Equipment(['tracking_mode' => TrackingMode::Serialized, 'unit' => 'unit'])));
    }

    public function store(EquipmentRequest $request): RedirectResponse
    {
        $equipment = $this->service->create($request->equipmentData(), $request->file('image'));

        return redirect()->route('app.inventory.equipment.show', $equipment)->with('success', "{$equipment->name} added to the catalogue.");
    }

    public function show(Request $request, Equipment $equipment): View
    {
        $this->authorize('view', $equipment);

        $tab = in_array($request->query('tab'), ['units', 'stock', 'history'], true) ? $request->query('tab') : ($equipment->isSerialized() ? 'units' : 'stock');
        $equipment->load('category.parent');
        $equipment->loadCount(['assets as assets_archived' => fn ($q) => $q->onlyTrashed()]);
        $withAvailability = Equipment::withTrashed()->withAvailability()->find($equipment->id);

        $data = [
            'equipment' => $equipment,
            'summary' => $withAvailability,
            'tab' => $tab,
            'statusBreakdown' => $equipment->isSerialized()
                ? $equipment->assets()->with('status')->get()->groupBy('status_id')->map(fn ($g) => ['status' => $g->first()->status, 'count' => $g->count()])->sortBy('status.sort_order')->values()
                : collect(),
        ];

        if ($tab === 'units' && $equipment->isSerialized()) {
            $statusFilter = $request->integer('status') ?: null;
            $data['assets'] = $equipment->assets()
                ->with(['status', 'location'])
                ->when($request->boolean('archived'), fn ($q) => $q->onlyTrashed())
                ->when($statusFilter, fn ($q) => $q->where('status_id', $statusFilter))
                ->orderBy('asset_tag')->paginate(25, ['*'], 'page')->withQueryString();
            $data['statuses'] = AssetStatus::ordered();
            $data['locations'] = Location::options();
            $data['conditions'] = $this->lookups->options('condition');
            $data['manualStatuses'] = AssetStatus::ordered()->where('is_manual', true)->where('is_active', true)->pluck('label', 'id')->all();
        }

        if ($tab === 'stock' && ! $equipment->isSerialized()) {
            $data['levels'] = $equipment->stockLevels()->with('location')->where('quantity', '>', 0)->get()
                ->groupBy('location_id')->map(fn ($g) => [
                    'location' => $g->first()->location,
                    'available' => (int) $g->firstWhere('bucket', StockBucket::Available)?->quantity,
                    'quarantine' => (int) $g->firstWhere('bucket', StockBucket::Quarantine)?->quantity,
                ])->sortBy('location.name')->values();
            $data['locations'] = Location::options();
        }

        if ($tab === 'history') {
            $data['history'] = $equipment->transactions()->with(['equipment', 'asset', 'fromLocation', 'toLocation', 'fromStatus', 'toStatus'])->latest('occurred_at')->latest('id')->paginate(30)->withQueryString();
        }

        return view('internal.inventory.equipment.show', $data);
    }

    public function edit(Equipment $equipment): View
    {
        $this->authorize('update', $equipment);

        return view('internal.inventory.equipment.form', $this->formData($equipment));
    }

    public function update(EquipmentRequest $request, Equipment $equipment): RedirectResponse
    {
        $this->service->update($equipment, $request->equipmentData(), $request->file('image'), $request->boolean('remove_image'));

        return redirect()->route('app.inventory.equipment.show', $equipment)->with('success', 'Changes saved.');
    }

    public function destroy(Equipment $equipment): RedirectResponse
    {
        $this->authorize('delete', $equipment);
        $this->service->archive($equipment);

        return redirect()->route('app.inventory.equipment.index')->with('success', "{$equipment->name} archived. Its history is kept.");
    }

    public function restore(int $id): RedirectResponse
    {
        $equipment = Equipment::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $equipment);
        $this->service->restore($equipment);

        return redirect()->route('app.inventory.equipment.show', $equipment)->with('success', "{$equipment->name} restored.");
    }

    /** Streams the private image to signed-in users with inventory access. */
    public function image(Equipment $equipment): StreamedResponse
    {
        $this->authorize('view', $equipment);
        abort_unless($equipment->image_path && Storage::disk(ImageStore::DISK)->exists($equipment->image_path), 404);

        return Storage::disk(ImageStore::DISK)->response($equipment->image_path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Equipment $equipment): array
    {
        return [
            'equipment' => $equipment,
            'categories' => EquipmentCategory::options($equipment->category_id),
            'units' => $this->lookups->options('unit', $equipment->unit),
            'locked' => $equipment->exists && ($equipment->assets()->withTrashed()->exists() || $equipment->stockLevels()->exists()),
        ];
    }
}
