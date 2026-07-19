<?php

namespace Modules\UserManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the system role templates (business_id = null) and their permissions.
 * Tenants can add Custom Roles on top of these through Settings; this seeder
 * never runs per-tenant, only once at platform setup — new businesses simply
 * assign users to these existing role names.
 *
 * Permission granularity is coarse (one per module area) for the foundation
 * phase; later phases split these into view/create/edit/delete abilities as
 * each module ships, without renaming the roles that reference them.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'business.manage',
            'branches.manage',
            'users.manage',
            'roles.manage',
            'settings.manage',
            'audit.view',
            'products.manage',
            'inventory.manage',
            'pos.operate',
            'sales.manage',
            'purchases.manage',
            'customers.manage',
            'suppliers.manage',
            'accounting.manage',
            'reports.view',
            'notifications.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $rolePermissions = [
            'Owner' => $permissions,
            'Admin' => array_values(array_diff($permissions, ['business.manage'])),
            'Manager' => array_values(array_diff($permissions, [
                'business.manage', 'users.manage', 'roles.manage',
            ])),
            'Cashier' => ['pos.operate', 'sales.manage', 'customers.manage'],
            'Accountant' => ['accounting.manage', 'reports.view', 'purchases.manage', 'sales.manage'],
            'Inventory Manager' => ['inventory.manage', 'products.manage', 'purchases.manage', 'suppliers.manage'],
        ];

        foreach ($rolePermissions as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'business_id' => null]);
            $role->syncPermissions($rolePerms);
        }
    }
}
