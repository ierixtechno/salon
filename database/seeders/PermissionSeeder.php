<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Permission *definitions* are global platform vocabulary, shared across
 * every tenant (unlike roles, which are team/tenant-scoped — see
 * docs/04-RBAC.md). This seeder only ever adds to the catalog as new
 * modules ship; it never assigns permissions to any tenant's roles — that
 * happens per-tenant in OnboardTenant / role management.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Phase 1: platform/tenant administration
            'tenant.settings.manage',
            'branches.view',
            'branches.create',
            'branches.update',
            'branches.delete',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.manage',
            'subscription.view',

            // Phase 2: employees and resources
            'employees.view',
            'employees.create',
            'employees.update',
            'employees.delete',
            'resources.view',
            'resources.create',
            'resources.update',
            'resources.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}
