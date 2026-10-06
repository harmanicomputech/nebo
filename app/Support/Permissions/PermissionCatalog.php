<?php

namespace App\Support\Permissions;

/**
 * Every permission the application checks, grouped by module.
 *
 * Permissions belong with the code that checks them, so they are defined
 * here and synced to the database by RolesAndPermissionsSeeder. Roles are
 * data: administrators edit them in the console. The default role matrix
 * below is only applied when a role is first created.
 */
class PermissionCatalog
{
    public const SUPER_ADMIN = 'Super Administrator';

    /**
     * @return array<string, array{label: string, permissions: array<string, string>}>
     */
    public static function modules(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'permissions' => [
                'dashboard.view' => 'View the operations dashboard',
            ]],
            'users' => ['label' => 'Users', 'permissions' => [
                'users.view' => 'View user accounts',
                'users.create' => 'Create user accounts',
                'users.update' => 'Edit users and their roles',
                'users.deactivate' => 'Deactivate and reactivate users',
            ]],
            'roles' => ['label' => 'Roles & permissions', 'permissions' => [
                'roles.view' => 'View roles and their permissions',
                'roles.manage' => 'Create, edit and delete roles',
            ]],
            'settings' => ['label' => 'Settings', 'permissions' => [
                'settings.view' => 'View system settings',
                'settings.manage' => 'Change system settings and option lists',
            ]],
            'audit' => ['label' => 'Audit log', 'permissions' => [
                'audit.view' => 'View the audit log',
            ]],
            'inventory' => ['label' => 'Inventory', 'permissions' => [
                'inventory.view' => 'View equipment, assets and stock',
                'inventory.create' => 'Add equipment and assets',
                'inventory.update' => 'Edit equipment and assets',
                'inventory.archive' => 'Archive or retire equipment and assets',
                'inventory.adjust' => 'Adjust stock and transfer between locations',
                'inventory.configure' => 'Manage categories, locations and asset statuses',
                'inventory.costs' => 'See purchase costs and asset values',
            ]],
            'requests' => ['label' => 'Booking requests', 'permissions' => [
                'requests.view' => 'View event production requests',
                'requests.manage' => 'Assign, annotate and convert requests',
                'requests.status' => 'Change request status',
            ]],
            'events' => ['label' => 'Events & production', 'permissions' => [
                'events.view' => 'View events',
                'events.view_assigned' => 'View only events the user is assigned to',
                'events.create' => 'Create events',
                'events.update' => 'Edit events and production requirements',
                'events.status' => 'Change event status',
                'events.archive' => 'Archive events',
                'events.team' => 'Assign the event team',
            ]],
            'allocation' => ['label' => 'Availability & allocation', 'permissions' => [
                'allocation.view' => 'View availability and allocations',
                'allocation.manage' => 'Reserve and allocate equipment',
                'loadlists.manage' => 'Prepare and check load lists',
                'returns.manage' => 'Check equipment back in',
            ]],
            'maintenance' => ['label' => 'Maintenance', 'permissions' => [
                'maintenance.view' => 'View maintenance and inspections',
                'maintenance.view_assigned' => 'View only maintenance assigned to the user',
                'maintenance.manage' => 'Record maintenance, inspections and damage',
                'maintenance.schedule' => 'Manage maintenance schedules',
            ]],
            'logistics' => ['label' => 'Logistics & fleet', 'permissions' => [
                'logistics.view' => 'View logistics and vehicles',
                'logistics.view_assigned' => 'View and update only trips the user drives or crews',
                'logistics.manage' => 'Plan trips, dispatch and deliveries',
                'vehicles.manage' => 'Manage vehicles',
            ]],
            'staff' => ['label' => 'Staff & teams', 'permissions' => [
                'staff.view' => 'View staff profiles',
                'staff.manage' => 'Manage staff profiles',
            ]],
            'customers' => ['label' => 'Customers', 'permissions' => [
                'customers.view' => 'View customers',
                'customers.manage' => 'Create, edit and merge customers',
            ]],
            'quotations' => ['label' => 'Quotations & packages', 'permissions' => [
                'quotations.view' => 'View quotations',
                'quotations.manage' => 'Prepare and edit quotations',
                'quotations.approve' => 'Approve and send quotations',
                'packages.manage' => 'Manage production packages',
                'services.manage' => 'Manage the services catalogue',
            ]],
            'financial' => ['label' => 'Financial information', 'permissions' => [
                'financial.view' => 'See budgets, values and pipeline figures',
            ]],
            'documents' => ['label' => 'Documents', 'permissions' => [
                'documents.view' => 'View and download documents',
                'documents.manage' => 'Upload and remove documents',
            ]],
            'reports' => ['label' => 'Reports', 'permissions' => [
                'reports.view' => 'View operational reports',
                'reports.financial' => 'View commercial and financial reports',
                'reports.export' => 'Export reports',
            ]],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge(...array_map(
            fn (array $module) => array_keys($module['permissions']),
            array_values(self::modules()),
        ));
    }

    public static function label(string $permission): string
    {
        foreach (self::modules() as $module) {
            if (isset($module['permissions'][$permission])) {
                return $module['permissions'][$permission];
            }
        }

        return $permission;
    }

    /**
     * Roles from the brief and their starting permissions. '*' means the role
     * is granted everything through Gate::before (see AppServiceProvider).
     *
     * @return array<string, array{description: string, permissions: list<string>|string}>
     */
    public static function defaultRoles(): array
    {
        $view = fn (string ...$modules) => array_values(array_filter(
            self::all(),
            fn (string $p) => in_array(explode('.', $p)[0], $modules, true) && str_ends_with($p, '.view'),
        ));

        return [
            self::SUPER_ADMIN => [
                'description' => 'Full access to everything, including users, roles and settings.',
                'permissions' => '*',
            ],
            'Operations Manager' => [
                'description' => 'Runs events, inventory, logistics and production.',
                'permissions' => [
                    'dashboard.view', 'audit.view', 'settings.view', 'users.view',
                    'inventory.view', 'inventory.create', 'inventory.update', 'inventory.archive', 'inventory.adjust', 'inventory.configure', 'inventory.costs',
                    'requests.view', 'requests.manage', 'requests.status',
                    'events.view', 'events.create', 'events.update', 'events.status', 'events.archive', 'events.team',
                    'allocation.view', 'allocation.manage', 'loadlists.manage', 'returns.manage',
                    'maintenance.view', 'maintenance.manage', 'maintenance.schedule',
                    'logistics.view', 'logistics.manage', 'vehicles.manage',
                    'staff.view', 'staff.manage', 'customers.view', 'quotations.view', 'financial.view',
                    'documents.view', 'documents.manage', 'reports.view', 'reports.export',
                ],
            ],
            'Inventory Manager' => [
                'description' => 'Inventory, equipment, allocation, returns and maintenance.',
                'permissions' => [
                    'dashboard.view',
                    'inventory.view', 'inventory.create', 'inventory.update', 'inventory.archive', 'inventory.adjust', 'inventory.configure', 'inventory.costs',
                    'events.view', 'allocation.view', 'allocation.manage', 'loadlists.manage', 'returns.manage',
                    'maintenance.view', 'maintenance.manage', 'maintenance.schedule',
                    'logistics.view', 'documents.view', 'documents.manage', 'reports.view', 'reports.export',
                ],
            ],
            'Production Manager' => [
                'description' => 'Events, production planning and equipment requirements.',
                'permissions' => [
                    'dashboard.view', 'inventory.view',
                    'requests.view', 'requests.manage', 'requests.status',
                    'events.view', 'events.create', 'events.update', 'events.status', 'events.team',
                    'allocation.view', 'allocation.manage', 'loadlists.manage',
                    'maintenance.view', 'logistics.view', 'staff.view', 'customers.view',
                    'documents.view', 'documents.manage', 'reports.view',
                ],
            ],
            'Finance / Commercial' => [
                'description' => 'Customers, quotations and financial information.',
                'permissions' => [
                    'dashboard.view', 'requests.view', 'events.view',
                    'customers.view', 'customers.manage',
                    'quotations.view', 'quotations.manage', 'quotations.approve', 'packages.manage', 'services.manage',
                    'financial.view', 'inventory.costs', 'documents.view', 'documents.manage',
                    'reports.view', 'reports.financial', 'reports.export',
                ],
            ],
            'Technician' => [
                'description' => 'Assigned equipment, maintenance and inspections.',
                'permissions' => [
                    'dashboard.view', 'inventory.view', 'events.view_assigned', 'allocation.view',
                    'returns.manage', 'maintenance.view_assigned', 'maintenance.manage', 'documents.view',
                ],
            ],
            'Crew' => [
                'description' => 'Assigned events and operational tasks.',
                'permissions' => ['dashboard.view', 'events.view_assigned', 'loadlists.manage', 'logistics.view_assigned', 'documents.view'],
            ],
            'Viewer' => [
                'description' => 'Read-only access to operational information.',
                'permissions' => array_merge(['dashboard.view'], $view(
                    'inventory', 'requests', 'events', 'allocation', 'maintenance', 'logistics', 'staff', 'customers', 'documents', 'reports',
                )),
            ],
        ];
    }
}
