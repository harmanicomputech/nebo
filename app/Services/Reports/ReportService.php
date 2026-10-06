<?php

namespace App\Services\Reports;

use App\Enums\AllocationState;
use App\Enums\AssetStatusGroup;
use App\Enums\EventStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\QuotationStatus;
use App\Enums\StockBucket;
use App\Enums\TripDirection;
use App\Enums\TripStatus;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentAsset;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\LogisticsTrip;
use App\Models\MaintenanceRecord;
use App\Models\Quotation;
use App\Models\StockLevel;
use App\Models\Vehicle;
use App\Support\Lookups;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Report figures (D65). Each method returns the numbers a report page shows,
 * plus a `table` (columns + rows) that the CSV export writes. Monthly
 * buckets are in Lagos time. Data volumes are an events company's, so rows
 * are fetched for the period and bucketed in PHP, which keeps the queries
 * identical on SQLite and MySQL.
 */
class ReportService
{
    public function __construct(private Lookups $lookups) {}

    /**
     * Unit-days booked ÷ unit-days owned, per item, for the period. A unit
     * counts as booked while an allocation holds it (reserved, out or
     * returned — released holds never happened).
     *
     * @return array<string, mixed>
     */
    public function utilisation(ReportPeriod $period): array
    {
        $from = $period->fromUtc();
        $to = $period->toUtc();
        $days = $period->days();

        $holds = EquipmentAllocation::query()
            ->whereIn('state', [AllocationState::Reserved, AllocationState::CheckedOut, AllocationState::Returned])
            ->where('hold_starts_at', '<', $to)->where('hold_ends_at', '>', $from)
            ->get(['equipment_id', 'quantity', 'hold_starts_at', 'hold_ends_at'])
            ->groupBy('equipment_id')
            ->map(fn (Collection $rows) => $rows->sum(fn ($a) => $a->quantity * $this->overlapDays($a->hold_starts_at, $a->hold_ends_at, $from, $to)));

        $serializedUnits = EquipmentAsset::query()
            ->whereHas('status', fn ($q) => $q->whereNotIn('group', [AssetStatusGroup::Retired->value, AssetStatusGroup::Lost->value]))
            ->selectRaw('equipment_id, count(*) as units')->groupBy('equipment_id')->pluck('units', 'equipment_id');
        $bulkUnits = StockLevel::query()->whereIn('bucket', [StockBucket::Available->value, StockBucket::Quarantine->value])
            ->selectRaw('equipment_id, sum(quantity) as units')->groupBy('equipment_id')->pluck('units', 'equipment_id');

        $rows = Equipment::query()->where('is_active', true)->with('category')->orderBy('name')->get()
            ->map(function (Equipment $e) use ($holds, $serializedUnits, $bulkUnits, $days) {
                $units = (int) ($e->isSerialized() ? ($serializedUnits[$e->id] ?? 0) : ($bulkUnits[$e->id] ?? 0));
                $booked = (float) ($holds[$e->id] ?? 0);
                $capacity = $units * $days;

                return [
                    'id' => $e->id, 'name' => $e->name, 'category' => $e->category?->name, 'units' => $units,
                    'booked_days' => round($booked, 1), 'capacity_days' => round($capacity, 1),
                    'pct' => $capacity > 0 ? round(min(100, $booked / $capacity * 100), 1) : 0.0,
                ];
            })
            ->filter(fn ($r) => $r['units'] > 0)
            ->sortByDesc('pct')->values();

        $totalBooked = $rows->sum('booked_days');
        $totalCapacity = $rows->sum('capacity_days');

        return [
            'rows' => $rows,
            'summary' => [
                'overall' => $totalCapacity > 0 ? round($totalBooked / $totalCapacity * 100, 1) : 0,
                'idle' => $rows->where('booked_days', 0)->count(),
                'busiest' => $rows->first(),
                'items' => $rows->count(),
            ],
            'table' => [
                'columns' => ['Item', 'Category', 'Units', 'Unit-days booked', 'Unit-days available', 'Utilisation %'],
                'rows' => $rows->map(fn ($r) => [$r['name'], $r['category'], $r['units'], $r['booked_days'], $r['capacity_days'], $r['pct']])->all(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function events(ReportPeriod $period): array
    {
        $events = Event::query()->where('starts_at', '>=', $period->fromUtc())->where('starts_at', '<=', $period->toUtc())
            ->with('customer')->get();
        $months = $period->months();
        $bucket = fn (Collection $rows) => array_values(array_map(fn ($key) => $rows->filter(fn ($e) => ReportPeriod::monthOf($e->starts_at) === $key)->count(), array_keys($months)));

        $requests = EventRequest::query()->whereBetween('created_at', [$period->fromUtc(), $period->toUtc()])->withCount('quotations')->get();

        return [
            'months' => array_values($months),
            'series' => [
                ['name' => 'Completed', 'values' => $bucket($events->where('status', EventStatus::Completed))],
                ['name' => 'Planned or live', 'values' => $bucket($events->filter(fn ($e) => $e->status->holdsResources()))],
                ['name' => 'Cancelled', 'values' => $bucket($events->where('status', EventStatus::Cancelled))],
            ],
            'byType' => $events->groupBy('event_type')->map(fn ($g, $type) => ['label' => $this->lookups->label('event_type', $type), 'value' => $g->count()])->sortByDesc('value')->values()->all(),
            'byCustomer' => $events->groupBy('customer_id')->map(fn ($g) => ['label' => $g->first()->customer?->displayName() ?? '—', 'value' => $g->count(), 'url' => $g->first()->customer ? route('app.customers.show', $g->first()->customer) : null])->sortByDesc('value')->take(8)->values()->all(),
            'funnel' => [
                ['label' => 'Requests received', 'value' => $requests->count()],
                ['label' => 'Quoted', 'value' => $requests->filter(fn ($r) => $r->quotations_count > 0)->count()],
                ['label' => 'Won', 'value' => $requests->filter(fn ($r) => $r->status->isWon())->count()],
                ['label' => 'Became events', 'value' => $requests->whereNotNull('converted_event_id')->count()],
            ],
            'summary' => [
                'total' => $events->count(),
                'completed' => $events->where('status', EventStatus::Completed)->count(),
                'cancelled' => $events->where('status', EventStatus::Cancelled)->count(),
                'winRate' => $requests->count() ? round($requests->filter(fn ($r) => $r->status->isWon())->count() / $requests->count() * 100) : null,
            ],
            'table' => [
                'columns' => ['Reference', 'Event', 'Customer', 'Type', 'Starts', 'Status'],
                'rows' => $events->sortBy('starts_at')->map(fn ($e) => [$e->reference, $e->name, $e->customer?->displayName(), $this->lookups->label('event_type', $e->event_type),
                    $e->starts_at->copy()->setTimezone(config('nebo.display_timezone'))->format('Y-m-d H:i'), $e->status->label()])->values()->all(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function maintenance(ReportPeriod $period, bool $withCosts): array
    {
        $jobs = MaintenanceRecord::query()->whereBetween('created_at', [$period->fromUtc(), $period->toUtc()])->with(['asset', 'equipment'])->get();
        $completed = MaintenanceRecord::query()->where('status', MaintenanceStatus::Completed)->whereBetween('completed_at', [$period->fromUtc(), $period->toUtc()])->with('equipment')->get();
        $months = $period->months();
        $count = fn (Collection $rows, string $field) => array_values(array_map(fn ($key) => $rows->filter(fn ($r) => ReportPeriod::monthOf($r->{$field}) === $key)->count(), array_keys($months)));
        $turnaround = $completed->map(fn ($r) => $r->created_at->diffInHours($r->completed_at) / 24);

        return [
            'months' => array_values($months),
            'series' => [['name' => 'Reported', 'values' => $count($jobs, 'created_at')], ['name' => 'Completed', 'values' => $count($completed, 'completed_at')]],
            'byType' => $jobs->groupBy('type')->map(fn ($g) => ['label' => $g->first()->typeLabel(), 'value' => $g->count()])->sortByDesc('value')->values()->all(),
            'byItem' => $jobs->groupBy('equipment_id')->map(fn ($g) => ['label' => $g->first()->equipment->name, 'value' => $g->count()])->sortByDesc('value')->take(8)->values()->all(),
            'costByItem' => $withCosts ? $completed->whereNotNull('cost_kobo')->groupBy('equipment_id')->map(fn ($g) => ['label' => $g->first()->equipment->name, 'value' => (int) $g->sum('cost_kobo')])->sortByDesc('value')->take(8)->values()->all() : [],
            'summary' => [
                'reported' => $jobs->count(),
                'completed' => $completed->count(),
                'open' => MaintenanceRecord::query()->open()->count(),
                'turnaround' => $turnaround->isNotEmpty() ? round($turnaround->avg(), 1) : null,
                'cost' => $withCosts ? (int) $completed->sum('cost_kobo') : null,
            ],
            'table' => [
                'columns' => array_merge(['Reference', 'Asset', 'Item', 'Type', 'Priority', 'Status', 'Reported', 'Completed'], $withCosts ? ['Cost (NGN)'] : []),
                'rows' => $jobs->sortBy('created_at')->map(fn ($r) => array_merge([$r->reference, $r->asset?->asset_tag, $r->equipment->name, $r->typeLabel(), $r->priority->label(), $r->status->label(),
                    $r->created_at->setTimezone(config('nebo.display_timezone'))->format('Y-m-d'), $r->completed_at?->setTimezone(config('nebo.display_timezone'))->format('Y-m-d')],
                    $withCosts ? [$r->cost_kobo !== null ? $r->cost_kobo / 100 : null] : []))->values()->all(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function commercial(ReportPeriod $period): array
    {
        $sent = Quotation::query()->whereNotNull('sent_at')->whereBetween('sent_at', [$period->fromUtc(), $period->toUtc()])->with('customer')->get();
        $answered = Quotation::query()->whereIn('status', [QuotationStatus::Accepted, QuotationStatus::Declined])
            ->whereBetween('responded_at', [$period->fromUtc(), $period->toUtc()])->with('customer')->get();
        $accepted = $answered->where('status', QuotationStatus::Accepted);
        $expired = Quotation::query()->where('status', QuotationStatus::Expired)->whereBetween('valid_until', [$period->from->toDateString(), $period->to->toDateString()])->count();
        $months = $period->months();
        $decided = $answered->count() + $expired;

        return [
            'months' => array_values($months),
            'won' => [['name' => 'Accepted value', 'values' => array_values(array_map(fn ($key) => (int) $accepted->filter(fn ($q) => ReportPeriod::monthOf($q->responded_at) === $key)->sum('total_kobo'), array_keys($months)))]],
            'sentCount' => [['name' => 'Quotations sent', 'values' => array_values(array_map(fn ($key) => $sent->filter(fn ($q) => ReportPeriod::monthOf($q->sent_at) === $key)->count(), array_keys($months)))]],
            'topCustomers' => $accepted->groupBy('customer_id')->map(fn ($g) => ['label' => $g->first()->customer->displayName(), 'value' => (int) $g->sum('total_kobo'), 'url' => route('app.customers.show', $g->first()->customer)])->sortByDesc('value')->take(8)->values()->all(),
            'summary' => [
                'sent' => $sent->count(),
                'sentValue' => (int) $sent->sum('total_kobo'),
                'accepted' => $accepted->count(),
                'acceptedValue' => (int) $accepted->sum('total_kobo'),
                'conversion' => $decided ? round($accepted->count() / $decided * 100) : null,
                'average' => $accepted->count() ? (int) round($accepted->avg('total_kobo')) : null,
                'pipeline' => (int) Quotation::query()->where('status', QuotationStatus::Sent)->sum('total_kobo'),
            ],
            'table' => [
                'columns' => ['Reference', 'Title', 'Customer', 'Status', 'Sent', 'Answered', 'Total (NGN)'],
                'rows' => $sent->merge($answered)->unique('id')->sortBy('sent_at')->map(fn ($q) => [$q->label(), $q->title, $q->customer->displayName(), $q->status->label(),
                    $q->sent_at?->setTimezone(config('nebo.display_timezone'))->format('Y-m-d'), $q->responded_at?->setTimezone(config('nebo.display_timezone'))->format('Y-m-d'), $q->total_kobo / 100])->values()->all(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function logistics(ReportPeriod $period): array
    {
        $trips = LogisticsTrip::query()->where('status', '!=', TripStatus::Cancelled)
            ->whereBetween('departs_at', [$period->fromUtc(), $period->toUtc()])->with(['vehicle', 'driver', 'event'])->get();
        $months = $period->months();
        $bucket = fn (Collection $rows) => array_values(array_map(fn ($key) => $rows->filter(fn ($t) => ReportPeriod::monthOf($t->departs_at) === $key)->count(), array_keys($months)));
        $arrived = $trips->where('status', TripStatus::Arrived)->whereNotNull('arrived_at');
        $onTime = $arrived->filter(fn ($t) => $t->arrived_at->lte($t->arrives_at->copy()->addMinutes(30)));

        return [
            'months' => array_values($months),
            'series' => [
                ['name' => 'To venue', 'values' => $bucket($trips->where('direction', TripDirection::Outbound))],
                ['name' => 'Return', 'values' => $bucket($trips->where('direction', TripDirection::Return))],
                ['name' => 'Transfer', 'values' => $bucket($trips->where('direction', TripDirection::Transfer))],
            ],
            'byVehicle' => $trips->whereNotNull('vehicle_id')->groupBy('vehicle_id')->map(fn ($g) => ['label' => $g->first()->vehicle->name, 'value' => $g->count(), 'hint' => $g->first()->vehicle->registration])->sortByDesc('value')->values()->all(),
            'byDriver' => $trips->whereNotNull('driver_id')->groupBy('driver_id')->map(fn ($g) => ['label' => $g->first()->driver->name, 'value' => $g->count()])->sortByDesc('value')->take(8)->values()->all(),
            'summary' => [
                'trips' => $trips->count(),
                'arrived' => $arrived->count(),
                'onTime' => $arrived->count() ? round($onTime->count() / $arrived->count() * 100) : null,
                'vehicles' => Vehicle::query()->where('status', 'active')->count(),
            ],
            'table' => [
                'columns' => ['Reference', 'Direction', 'Event', 'From', 'To', 'Vehicle', 'Driver', 'Planned departure', 'Planned arrival', 'Arrived', 'Status'],
                'rows' => $trips->sortBy('departs_at')->map(fn ($t) => [$t->reference, $t->direction->label(), $t->event?->name, $t->origin, $t->destination, $t->vehicle?->registration, $t->driver?->name,
                    $this->local($t->departs_at), $this->local($t->arrives_at), $this->local($t->arrived_at), $t->status->label()])->values()->all(),
            ],
        ];
    }

    /** Current fleet, not a period. @return array<string, mixed> */
    public function inventory(bool $withCosts): array
    {
        $assets = EquipmentAsset::query()->with(['status', 'equipment.category'])->get();
        $byGroup = collect(AssetStatusGroup::cases())->map(fn ($g) => ['label' => $g->label(), 'value' => $assets->filter(fn ($a) => $a->status->group === $g)->count()])
            ->filter(fn ($r) => $r['value'] > 0)->values()->all();
        $byCategory = $assets->groupBy(fn ($a) => $a->equipment->category?->name ?? 'Uncategorised')
            ->map(fn ($g, $name) => ['label' => $name, 'value' => $g->count(), 'worth' => (int) $g->sum(fn ($a) => $a->current_value_kobo ?? $a->purchase_cost_kobo ?? $a->equipment->replacement_value_kobo ?? 0)])
            ->sortByDesc('value')->values();

        return [
            'byGroup' => $byGroup,
            'byCategory' => $byCategory->map(fn ($r) => ['label' => $r['label'], 'value' => $r['value']])->all(),
            'valueByCategory' => $withCosts ? $byCategory->map(fn ($r) => ['label' => $r['label'], 'value' => $r['worth']])->sortByDesc('value')->values()->all() : [],
            'summary' => [
                'units' => $assets->count(),
                'inService' => $assets->filter(fn ($a) => in_array($a->status->group, [AssetStatusGroup::Available, AssetStatusGroup::Committed, AssetStatusGroup::Out], true))->count(),
                'attention' => $assets->filter(fn ($a) => in_array($a->status->group, [AssetStatusGroup::Attention, AssetStatusGroup::Damaged], true))->count(),
                'value' => $withCosts ? (int) $byCategory->sum('worth') : null,
            ],
            'table' => [
                'columns' => array_merge(['Asset tag', 'Item', 'Category', 'Status', 'Condition', 'Location'], $withCosts ? ['Value (NGN)'] : []),
                'rows' => EquipmentAsset::query()->with(['status', 'equipment.category', 'location'])->orderBy('asset_tag')->get()
                    ->map(fn ($a) => array_merge([$a->asset_tag, $a->equipment->name, $a->equipment->category?->name, $a->status->label, $a->conditionLabel(), $a->location?->name],
                        $withCosts ? [($a->current_value_kobo ?? $a->purchase_cost_kobo ?? $a->equipment->replacement_value_kobo) !== null ? ($a->current_value_kobo ?? $a->purchase_cost_kobo ?? $a->equipment->replacement_value_kobo) / 100 : null] : []))->all(),
            ],
        ];
    }

    private function overlapDays(CarbonInterface $start, CarbonInterface $end, CarbonInterface $from, CarbonInterface $to): float
    {
        $s = $start->gt($from) ? $start : $from;
        $e = $end->lt($to) ? $end : $to;

        return max(0, $s->diffInSeconds($e, false) / 86400);
    }

    private function local(?CarbonInterface $at): ?string
    {
        return $at?->copy()->setTimezone(config('nebo.display_timezone'))->format('Y-m-d H:i');
    }
}
