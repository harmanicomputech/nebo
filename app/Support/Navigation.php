<?php

namespace App\Support;

use App\Models\Event;
use App\Models\LogisticsTrip;
use App\Models\MaintenanceRecord;
use App\Models\ProductionPackage;
use App\Models\User;
use Closure;

/**
 * The internal sidebar. Items without a route are modules from later phases:
 * they are shown greyed out with a "Planned" tag so nothing looks operational
 * before it is.
 */
class Navigation
{
    /**
     * Working modules grouped into sections, then the planned modules the
     * user will get access to, kept apart so they never crowd out live links.
     *
     * @return array{sections: list<array{label: string, items: list<array{label: string, icon: string, route: ?string, active: string|list<string>, permission: ?string, phase: ?int}>}>, planned: list<array{label: string, icon: string, route: ?string, active: string|list<string>, permission: ?string, phase: ?int}>}
     */
    public static function for(User $user): array
    {
        // Crew and technicians see their assigned events (events.view_assigned).
        $seesEvents = fn (User $u) => $u->can('viewAny', Event::class);

        $sections = [
            ['label' => 'Overview', 'items' => [
                self::item('Dashboard', 'layout-dashboard', 'app.dashboard', 'dashboard.view'),
                self::item('Calendar', 'calendar-days', 'app.calendar', $seesEvents),
            ]],
            ['label' => 'Operations', 'items' => [
                self::item('Requests', 'inbox', 'app.requests.index', 'requests.view'),
                self::item('Events', 'calendar-range', 'app.events.index', $seesEvents),
                self::item('Availability', 'layers', 'app.availability', 'allocation.view'),
                self::item('Load lists', 'clipboard-check', 'app.load-lists.index', fn (User $u) => $u->can('allocation.view') || $u->can('loadlists.manage')),
                self::item('Logistics', 'truck', 'app.logistics.index', fn (User $u) => $u->can('viewAny', LogisticsTrip::class), ['app.logistics.index', 'app.logistics.trips.*']),
                self::item('Fleet', 'car-front', 'app.logistics.vehicles.index', 'logistics.view', 'app.logistics.vehicles.*'),
            ]],
            ['label' => 'Inventory', 'items' => [
                self::item('Equipment', 'boxes', 'app.inventory.equipment.index', 'inventory.view', 'app.inventory.equipment.*'),
                self::item('Assets', 'qr-code', 'app.inventory.assets.index', 'inventory.view'),
                self::item('Stock movements', 'history', 'app.inventory.movements', 'inventory.view'),
                self::item('Inventory setup', 'warehouse', 'app.inventory.setup.categories', 'inventory.configure', 'app.inventory.setup.*'),
                self::item('Maintenance', 'wrench', 'app.maintenance.index', fn (User $u) => $u->can('viewAny', MaintenanceRecord::class), 'app.maintenance.*'),
            ]],
            ['label' => 'Commercial', 'items' => [
                self::item('Services', 'sparkles', 'app.settings.services', 'services.manage', 'app.settings.services*'),
                self::item('Customers', 'contact', 'app.customers.index', 'customers.view', 'app.customers.*'),
                self::item('Quotations', 'receipt', 'app.quotations.index', 'quotations.view', 'app.quotations.*'),
                self::item('Packages', 'package', 'app.packages.index', fn (User $u) => $u->can('viewAny', ProductionPackage::class), 'app.packages.*'),
                self::item('Reports', 'chart-column', 'app.reports.index', 'reports.view', 'app.reports.*'),
            ]],
            ['label' => 'Administration', 'items' => [
                self::item('Staff & crew', 'contact', 'app.staff.index', 'staff.view'),
                self::item('Users', 'users', 'app.users.index', 'users.view'),
                self::item('Roles & permissions', 'shield-check', 'app.roles.index', 'roles.view'),
                self::item('Audit log', 'scroll-text', 'app.audit.index', 'audit.view'),
                self::item('Settings', 'settings', 'app.settings.edit', 'settings.view', ['app.settings.edit', 'app.settings.options*']),
            ]],
        ];

        $visible = fn (array $item) => match (true) {
            $item['permission'] === null => true,
            $item['permission'] instanceof Closure => ($item['permission'])($user),
            default => $user->can($item['permission']),
        };
        $live = [];
        $planned = [];

        foreach ($sections as $section) {
            foreach (array_filter($section['items'], $visible) as $item) {
                $item['route'] ? $live[$section['label']][] = $item : $planned[] = $item;
            }
        }

        usort($planned, fn (array $a, array $b) => $a['phase'] <=> $b['phase']);

        return [
            'sections' => array_map(fn (string $label, array $items) => ['label' => $label, 'items' => $items], array_keys($live), $live),
            'planned' => $planned,
        ];
    }

    /**
     * @return array{label: string, icon: string, route: ?string, active: string|list<string>, permission: ?string, phase: ?int}
     */
    private static function item(string $label, string $icon, string $route, string|Closure|null $permission, string|array|null $active = null): array
    {
        // A resource index (app.users.index) is active on all of app.users.*; anything else only on itself.
        $active ??= str_ends_with($route, '.index') ? substr($route, 0, -strlen('index')).'*' : $route;

        return ['label' => $label, 'icon' => $icon, 'route' => $route, 'active' => $active, 'permission' => $permission, 'phase' => null];
    }

    /**
     * @return array{label: string, icon: string, route: ?string, active: string|list<string>, permission: ?string, phase: ?int}
     */
    private static function planned(string $label, string $icon, ?string $permission, int $phase): array
    {
        return ['label' => $label, 'icon' => $icon, 'route' => null, 'active' => '', 'permission' => $permission, 'phase' => $phase];
    }
}
