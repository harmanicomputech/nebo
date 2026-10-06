<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 1 dashboard: account and system facts that exist today. Operational
 * panels (events, inventory, maintenance) are added by their phases; until
 * then the roadmap panel says plainly what is coming.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

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
            'roadmap' => [
                ['phase' => 2, 'name' => 'Inventory', 'icon' => 'boxes', 'text' => 'Equipment catalogue, serialized assets, bulk stock, locations and the inventory ledger.'],
                ['phase' => 3, 'name' => 'Public booking', 'icon' => 'inbox', 'text' => 'Event Production Request form, customer matching, uploads and the request workflow.'],
                ['phase' => 4, 'name' => 'Events & production', 'icon' => 'calendar-range', 'text' => 'Event workspace, team assignment and the production calendar.'],
                ['phase' => 5, 'name' => 'Availability & allocation', 'icon' => 'layers', 'text' => 'Conflict detection, allocation, load lists, check-out and returns.'],
            ],
        ]);
    }
}
