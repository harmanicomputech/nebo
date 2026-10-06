<?php

namespace App\Http\Controllers\Internal\Inventory;

use App\Enums\InventoryTransactionType;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\InventoryTransaction;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The inventory ledger across all equipment (read-only). */
class MovementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Equipment::class);

        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(InventoryTransactionType::class)],
            'location' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $tz = config('nebo.display_timezone');

        $movements = InventoryTransaction::query()
            ->with(['equipment', 'asset', 'fromLocation', 'toLocation', 'fromStatus', 'toStatus'])
            ->when($filters['type'] ?? null, fn (Builder $q, $t) => $q->where('type', $t))
            ->when($filters['location'] ?? null, fn (Builder $q, $l) => $q->where(fn ($w) => $w->where('from_location_id', $l)->orWhere('to_location_id', $l)))
            ->when($filters['q'] ?? null, function (Builder $q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(fn ($w) => $w->whereHas('equipment', fn ($e) => $e->where('name', 'like', $like)->orWhere('sku', 'like', $like))
                    ->orWhereHas('asset', fn ($a) => $a->withTrashed()->where('asset_tag', 'like', $like))
                    ->orWhere('note', 'like', $like));
            })
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('occurred_at', '>=', now($tz)->parse($d, $tz)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('occurred_at', '<=', now($tz)->parse($d, $tz)->endOfDay()->utc()))
            ->latest('occurred_at')->latest('id')
            ->paginate(40)->withQueryString();

        return view('internal.inventory.movements', [
            'movements' => $movements,
            'filters' => $filters,
            'types' => collect(InventoryTransactionType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all(),
            'locations' => Location::options(),
        ]);
    }
}
