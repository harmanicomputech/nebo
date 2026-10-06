<?php

namespace App\Http\Controllers\Internal\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AssetRequest;
use App\Models\AssetStatus;
use App\Models\Equipment;
use App\Models\EquipmentAsset;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Services\Inventory\AssetService;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function __construct(private AssetService $assets, private Lookups $lookups) {}

    /** Every serialized unit across the catalogue. */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', EquipmentAsset::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'status' => ['nullable', 'integer'],
            'group' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'integer'],
            'condition' => ['nullable', 'string', 'max:50'],
            'archived' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:tag,equipment,updated'],
        ]);

        $query = EquipmentAsset::query()
            ->with(['equipment.category', 'status', 'location'])
            ->when($filters['archived'] ?? false, fn (Builder $q) => $q->onlyTrashed())
            ->search($filters['q'] ?? null)
            ->when($filters['category'] ?? null, function (Builder $q, $id) {
                $ids = EquipmentCategory::withTrashed()->find($id)?->selfAndChildIds() ?? [0];
                $q->whereHas('equipment', fn ($e) => $e->whereIn('category_id', $ids));
            })
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->where('status_id', $s))
            ->when($filters['group'] ?? null, fn (Builder $q, $g) => $q->whereHas('status', fn ($s) => $s->where('group', $g)))
            ->when($filters['location'] ?? null, fn (Builder $q, $l) => $q->where('location_id', $l))
            ->when($filters['condition'] ?? null, fn (Builder $q, $c) => $q->where('condition', $c));

        match ($filters['sort'] ?? 'tag') {
            'equipment' => $query->orderBy(Equipment::select('name')->whereColumn('equipment.id', 'equipment_assets.equipment_id'))->orderBy('asset_tag'),
            'updated' => $query->latest('updated_at'),
            default => $query->orderBy('asset_tag'),
        };

        return view('internal.inventory.assets.index', [
            'assets' => $query->paginate(30)->withQueryString(),
            'filters' => $filters,
            'categories' => EquipmentCategory::options(),
            'statuses' => AssetStatus::ordered()->pluck('label', 'id')->all(),
            'locations' => Location::options(),
            'conditions' => $this->lookups->options('condition'),
        ]);
    }

    public function create(Equipment $equipment): View
    {
        $this->authorize('addAssets', $equipment);

        return view('internal.inventory.assets.create', [
            'equipment' => $equipment,
            'statuses' => AssetStatus::ordered()->where('is_manual', true)->where('is_active', true)->pluck('label', 'id')->all(),
            'defaultStatus' => AssetStatus::byCode('available')->id,
            'locations' => Location::options(),
            'conditions' => $this->lookups->options('condition'),
        ]);
    }

    public function store(AssetRequest $request, Equipment $equipment): RedirectResponse
    {
        $count = $request->integer('count', 1);
        $created = $this->assets->register($equipment, $request->assetData(), $count);

        return $count === 1
            ? redirect()->route('app.inventory.assets.show', $created->first())->with('success', "{$created->first()->asset_tag} added.")
            : redirect()->route('app.inventory.equipment.show', [$equipment, 'tab' => 'units'])->with('success', "{$count} units added: {$created->first()->asset_tag} to {$created->last()->asset_tag}.");
    }

    public function show(EquipmentAsset $asset): View
    {
        $this->authorize('view', $asset);
        $asset->load(['equipment.category.parent', 'status', 'location']);

        return view('internal.inventory.assets.show', [
            'asset' => $asset,
            'bookings' => $asset->allocations()->active()->with('event')->orderBy('hold_starts_at')->get(),
            'history' => $asset->transactions()->with(['fromLocation', 'toLocation', 'fromStatus', 'toStatus'])->latest('occurred_at')->latest('id')->paginate(20),
            'manualStatuses' => AssetStatus::ordered()->where('is_manual', true)->where('is_active', true)->pluck('label', 'id')->all(),
            'locations' => Location::options(),
            'conditions' => $this->lookups->options('condition', $asset->condition),
        ]);
    }

    public function edit(EquipmentAsset $asset): View
    {
        $this->authorize('update', $asset);

        return view('internal.inventory.assets.edit', ['asset' => $asset->load('equipment')]);
    }

    public function update(AssetRequest $request, EquipmentAsset $asset): RedirectResponse
    {
        $asset->update($request->assetData());

        return redirect()->route('app.inventory.assets.show', $asset)->with('success', 'Changes saved.');
    }

    public function status(Request $request, EquipmentAsset $asset): RedirectResponse
    {
        $this->authorize('changeState', $asset);
        $data = $request->validate([
            'status_id' => ['required', 'integer', Rule::exists('asset_statuses', 'id')],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->assets->changeStatus($request->user(), $asset, AssetStatus::findOrFail($data['status_id']), $data['note'] ?? null);

        return back()->with('success', "{$asset->asset_tag} is now {$asset->status->label}.");
    }

    public function move(Request $request, EquipmentAsset $asset): RedirectResponse
    {
        $this->authorize('changeState', $asset);
        $data = $request->validate([
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $location = Location::findOrFail($data['location_id']);
        $this->assets->move($asset, $location, $data['note'] ?? null);

        return back()->with('success', "{$asset->asset_tag} moved to {$location->name}.");
    }

    public function condition(Request $request, EquipmentAsset $asset): RedirectResponse
    {
        $this->authorize('changeState', $asset);
        $data = $request->validate([
            'condition' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->assets->recordCondition($asset, $data['condition'], $data['note'] ?? null);

        return back()->with('success', "Condition recorded for {$asset->asset_tag}: {$asset->conditionLabel()}.");
    }

    public function destroy(Request $request, EquipmentAsset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);
        $this->assets->archive($asset, $request->string('note')->limit(500)->toString() ?: null);

        return redirect()->route('app.inventory.equipment.show', [$asset->equipment_id, 'tab' => 'units'])->with('success', "{$asset->asset_tag} archived. Its history is kept.");
    }

    public function restore(int $id): RedirectResponse
    {
        $asset = EquipmentAsset::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $asset);
        $this->assets->restore($asset);

        return redirect()->route('app.inventory.assets.show', $asset)->with('success', "{$asset->asset_tag} restored.");
    }
}
