<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Inventory\InventorySummary;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard: live figures for the modules that exist; the roadmap panel says
 * plainly what is coming. Each panel is gated by its module's permission.
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
            'inventory' => $canInventory ? [
                'groups' => $inventory->assetsByGroup(),
                'allocatable' => $inventory->allocatableAssets(),
                'bulk' => $inventory->bulkStock(),
                'lowStock' => $inventory->lowStock(),
                'lowStockCount' => $inventory->lowStockCount(),
                'maintenanceDue' => $inventory->maintenanceDue(),
            ] : null,
            'roadmap' => [
                ['phase' => 3, 'name' => 'Public booking', 'icon' => 'inbox', 'text' => 'Event Production Request form, customer matching, uploads and the request workflow.'],
                ['phase' => 4, 'name' => 'Events & production', 'icon' => 'calendar-range', 'text' => 'Event workspace, team assignment and the production calendar.'],
                ['phase' => 5, 'name' => 'Availability & allocation', 'icon' => 'layers', 'text' => 'Conflict detection, allocation, load lists, check-out and returns.'],
                ['phase' => 6, 'name' => 'Maintenance & condition', 'icon' => 'wrench', 'text' => 'Maintenance records and schedules, inspections and damage reports.'],
            ],
        ]);
    }
}
