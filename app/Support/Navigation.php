<?php

namespace App\Support;

use App\Models\User;

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
     * @return array{sections: list<array{label: string, items: list<array{label: string, icon: string, route: ?string, active: string, permission: ?string, phase: ?int}>}>, planned: list<array{label: string, icon: string, route: ?string, active: string, permission: ?string, phase: ?int}>}
     */
    public static function for(User $user): array
    {
        $sections = [
            ['label' => 'Overview', 'items' => [
                self::item('Dashboard', 'layout-dashboard', 'app.dashboard', 'dashboard.view'),
                self::planned('Calendar', 'calendar-days', 'events.view', 4),
            ]],
            ['label' => 'Operations', 'items' => [
                self::planned('Requests', 'inbox', 'requests.view', 3),
                self::planned('Events', 'calendar-range', 'events.view', 4),
                self::planned('Availability', 'layers', 'allocation.view', 5),
                self::planned('Load lists', 'clipboard-check', 'allocation.view', 5),
                self::planned('Logistics', 'truck', 'logistics.view', 7),
            ]],
            ['label' => 'Inventory', 'items' => [
                self::planned('Equipment', 'boxes', 'inventory.view', 2),
                self::planned('Locations', 'warehouse', 'inventory.configure', 2),
                self::planned('Maintenance', 'wrench', 'maintenance.view', 6),
            ]],
            ['label' => 'Commercial', 'items' => [
                self::planned('Customers', 'contact', 'customers.view', 8),
                self::planned('Quotations', 'receipt', 'quotations.view', 8),
                self::planned('Reports', 'chart-column', 'reports.view', 9),
            ]],
            ['label' => 'Administration', 'items' => [
                self::item('Users', 'users', 'app.users.index', 'users.view'),
                self::item('Roles & permissions', 'shield-check', 'app.roles.index', 'roles.view'),
                self::item('Audit log', 'scroll-text', 'app.audit.index', 'audit.view'),
                self::item('Settings', 'settings', 'app.settings.edit', 'settings.view'),
            ]],
        ];

        $visible = fn (array $item) => $item['permission'] === null || $user->can($item['permission']);
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
     * @return array{label: string, icon: string, route: ?string, active: string, permission: ?string, phase: ?int}
     */
    private static function item(string $label, string $icon, string $route, ?string $permission): array
    {
        // app.users.index is active on any app.users.* page; app.dashboard only on itself.
        $active = substr_count($route, '.') >= 2 ? preg_replace('/\.[a-z-]+$/', '.*', $route) : $route;

        return ['label' => $label, 'icon' => $icon, 'route' => $route, 'active' => $active, 'permission' => $permission, 'phase' => null];
    }

    /**
     * @return array{label: string, icon: string, route: ?string, active: string, permission: ?string, phase: ?int}
     */
    private static function planned(string $label, string $icon, ?string $permission, int $phase): array
    {
        return ['label' => $label, 'icon' => $icon, 'route' => null, 'active' => '', 'permission' => $permission, 'phase' => $phase];
    }
}
