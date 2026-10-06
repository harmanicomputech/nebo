<?php

namespace App\Http\Controllers\Internal;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentAsset;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\LoadList;
use App\Models\LogisticsTrip;
use App\Models\MaintenanceRecord;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Services\Inventory\InventorySummary;
use App\Services\Reports\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard: live figures and trends for each module. Each panel is gated by
 * its module's permission, so people only see what they work with.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, InventorySummary $inventory): View
    {
        $user = $request->user();
        $canInventory = $user->can('inventory.view');

        return view('internal.dashboard', [
            'user' => $user,
            'stats' => [
                'activeUsers' => $user->can('users.view') ? User::active()->count() : null,
                'roles' => $user->can('roles.view') ? Role::count() : null,
                'unread' => $user->unreadNotifications()->count(),
                'auditToday' => $user->can('audit.view') ? AuditLog::where('created_at', '>=', today())->count() : null,
            ],
            'activity' => $user->can('audit.view') ? AuditLog::latest('id')->limit(8)->get() : collect(),
            'notifications' => $user->unreadNotifications()->latest()->limit(5)->get(),
            'events' => $user->can('viewAny', Event::class) ? $this->events($user) : null,
            'operations' => $user->can('allocation.view') ? [
                'deployments' => LoadList::query()->where('status', '!=', 'dispatched')->whereHas('event', fn ($q) => $q->active()->where('setup_starts_at', '<=', now()->addDays(7)))->with('event')->withCount('items')->get()->sortBy('event.setup_starts_at')->values(),
                'out' => EquipmentAllocation::where('state', 'checked_out')->sum('quantity'),
                'overdue' => EquipmentAllocation::where('state', 'checked_out')->where('hold_ends_at', '<', now())->with('event')->get()->groupBy('event_id'),
                'inTransit' => EquipmentAsset::whereHas('status', fn ($q) => $q->where('code', 'in_transit'))->count(),
            ] : null,
            'trips' => $user->can('viewAny', LogisticsTrip::class)
                ? LogisticsTrip::query()->visibleTo($user)->active()
                    ->where('departs_at', '<', now(config('nebo.display_timezone'))->addDay()->endOfDay()->utc())
                    ->with(['vehicle', 'driver', 'event'])->withCount(['items', 'crew'])->orderBy('departs_at')->limit(8)->get()
                : null,
            'requests' => $user->can('requests.view') ? [
                'new' => EventRequest::where('status', RequestStatus::New)->count(),
                'open' => EventRequest::query()->open()->count(),
                'mine' => EventRequest::query()->open()->where('assigned_to', $user->id)->count(),
                'recent' => EventRequest::query()->open()->latest('id')->limit(5)->get(),
            ] : null,
            'inventory' => $canInventory ? [
                'groups' => $inventory->assetsByGroup(),
                'allocatable' => $inventory->allocatableAssets(),
                'bulk' => $inventory->bulkStock(),
                'lowStock' => $inventory->lowStock(),
                'lowStockCount' => $inventory->lowStockCount(),
                'maintenanceDue' => $inventory->maintenanceDue(),
                'openJobs' => $user->can('viewAny', MaintenanceRecord::class) ? MaintenanceRecord::query()->visibleTo($user)->open()->count() : null,
            ] : null,
            'trends' => $this->trends($user),
        ]);
    }

    /**
     * Small charts for the dashboard: events by month (3 back, 3 ahead) and
     * accepted quotation value for the last 6 months.
     *
     * @return array<string, mixed>
     */
    private function trends(User $user): array
    {
        $tz = config('nebo.display_timezone');
        $start = now($tz)->startOfMonth()->subMonths(3);
        $months = collect(range(0, 5))->mapWithKeys(fn ($i) => [$start->copy()->addMonths($i)->format('Y-m') => $start->copy()->addMonths($i)->format('M y')]);
        $events = $user->can('viewAny', Event::class)
            ? Event::query()->visibleTo($user)->where('status', '!=', 'cancelled')
                ->whereBetween('starts_at', [$start->copy()->utc(), $start->copy()->addMonths(6)->utc()])->pluck('starts_at')
            : null;

        $wonStart = now($tz)->startOfMonth()->subMonths(5);
        $wonMonths = collect(range(0, 5))->mapWithKeys(fn ($i) => [$wonStart->copy()->addMonths($i)->format('Y-m') => $wonStart->copy()->addMonths($i)->format('M y')]);
        $won = $user->can('financial.view') && $user->can('quotations.view')
            ? Quotation::query()->where('status', 'accepted')->where('responded_at', '>=', $wonStart->copy()->utc())->get(['responded_at', 'total_kobo'])
            : null;

        return [
            'months' => $months->values()->all(),
            'events' => $events === null ? null : [['name' => 'Events', 'values' => $months->keys()->map(fn ($k) => $events->filter(fn ($at) => ReportPeriod::monthOf($at) === $k)->count())->all()]],
            'wonMonths' => $wonMonths->values()->all(),
            'won' => $won === null ? null : [['name' => 'Accepted', 'values' => $wonMonths->keys()->map(fn ($k) => (int) $won->filter(fn ($q) => ReportPeriod::monthOf($q->responded_at) === $k)->sum('total_kobo'))->all()]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function events(User $user): array
    {
        $tz = config('nebo.display_timezone');
        $now = now($tz);
        $base = fn () => Event::query()->visibleTo($user)->active();

        return [
            'today' => $base()->overlapping($now->copy()->startOfDay()->utc(), $now->copy()->endOfDay()->utc())->with('customer')->orderBy('setup_starts_at')->get(),
            'week' => $base()->overlapping($now->copy()->startOfWeek()->utc(), $now->copy()->endOfWeek()->utc())->count(),
            'month' => $base()->overlapping($now->copy()->startOfMonth()->utc(), $now->copy()->endOfMonth()->utc())->count(),
            'upcoming' => $base()->where('setup_starts_at', '>', $now->copy()->endOfDay()->utc())->with('customer')->orderBy('setup_starts_at')->limit(5)->get(),
            'confirmed' => $base()->where('status', 'confirmed')->count(),
            'completed' => Event::query()->visibleTo($user)->where('status', 'completed')->count(),
        ];
    }
}
